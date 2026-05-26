<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\PaymentNotification;
use App\Models\PaymentRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Fiuu\FiuuGateway;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FiuuPaymentController extends Controller
{
    public function __construct(
        private FiuuGateway $fiuu,
        private AuditLogService $audit,
    ) {}

    public function create(Participant $participant): Response
    {
        if ($participant->isPaid()) {
            return response('Already paid', 400);
        }
        if ($participant->bill->status === 'draft') {
            return response('Bill not published', 400);
        }

        $orderId = $this->fiuu->generateOrderId();
        $payload = $this->fiuu->buildHostedPayload($participant, $orderId);

        $paymentRequest = PaymentRequest::create([
            'participant_id' => $participant->id,
            'bill_id' => $participant->bill_id,
            'order_id' => $orderId,
            'amount_cents' => $participant->amount_cents,
            'status' => 'pending',
            'request_payload_json' => $payload,
        ]);

        $participant->update(['status' => 'pending']);
        $this->audit->log('payment_started', $participant->bill, $participant, $paymentRequest);

        $action = $this->fiuu->getHostedUrl();

        return Inertia::render('Payment/Fiuu', [
            'action' => $action,
            'payload' => $payload,
        ]);
    }

    public function callback(Request $request): Response
    {
        // Verify webhook signature
        if (!$this->fiuu->verifySkey($request->all())) {
            Log::warning('Fiuu callback: invalid signature', ['payload' => $request->all()]);
            return response('Invalid signature', 403);
        }

        $payload = $request->all();
        $orderId = $payload['orderid'] ?? null;
        $tranID = $payload['tranID'] ?? null;
        $status = $payload['status'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!$orderId || !$tranID || !$status) {
            return response('Missing required fields', 400);
        }

        $paymentRequest = PaymentRequest::where('order_id', $orderId)->first();
        if (!$paymentRequest) {
            return response('Order not found', 404);
        }

        // Idempotency: check if notification already processed
        $existing = PaymentNotification::where('order_id', $orderId)
            ->where('tran_id', $tranID)
            ->exists();
        if ($existing) {
            return response('OK'); // already processed
        }

        DB::transaction(function () use ($paymentRequest, $payload, $orderId, $tranID, $status, $amount) {
            $isValidSignature = true; // already verified above
            PaymentNotification::create([
                'payment_request_id' => $paymentRequest->id,
                'order_id' => $orderId,
                'tran_id' => $tranID,
                'status' => $status,
                'amount' => $amount,
                'payload_json' => $payload,
                'is_valid_signature' => $isValidSignature,
                'received_at' => now(),
            ]);

            if ($status === '00') { // success
                $paymentRequest->update([
                    'status' => 'paid',
                    'fiuu_tran_id' => $tranID,
                    'fiuu_channel' => $payload['channel'] ?? null,
                    'paid_at' => now(),
                ]);
                $paymentRequest->participant->update(['status' => 'paid', 'paid_at' => now()]);
                $this->audit->log('payment_completed', $paymentRequest->bill, $paymentRequest->participant, $paymentRequest);
            } else {
                $paymentRequest->update(['status' => 'failed']);
                $paymentRequest->participant->update(['status' => 'failed']);
                $this->audit->log('payment_failed', $paymentRequest->bill, $paymentRequest->participant, $paymentRequest);
            }
        });

        return response('OK');
    }

    public function return(Request $request): Response
    {
        $orderId = $request->query('orderid');
        $paymentRequest = PaymentRequest::where('order_id', $orderId)->first();
        if (!$paymentRequest) {
            return redirect()->route('home')->with('error', 'Payment not found.');
        }

        return Inertia::render('Payment/Result', [
            'status' => $paymentRequest->status,
            'order_id' => $orderId,
            'amount' => Money::format($paymentRequest->amount_cents),
        ]);
    }
}
    }
}