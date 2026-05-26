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
        private FiuuGateway $gateway,
        private AuditLogService $audit,
    ) {}

    public function callback(Request $request): Response
    {
        // Verify webhook signature using SHA-512
        if (!$this->gateway->validateWebhook($request)) {
            Log::warning('Fiuu callback: invalid signature', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);
            return response('Invalid signature', 403);
        }

        $payload = $request->all();
        Log::info('Fiuu callback received', $payload);

        $orderId = $payload['order_id'] ?? null;
        if (!$orderId) {
            return response('Missing order_id', 400);
        }

        $paymentRequest = PaymentRequest::where('order_id', $orderId)->first();
        if (!$paymentRequest) {
            Log::warning('Fiuu callback: unknown order_id', ['order_id' => $orderId]);
            return response('Unknown order', 404);
        }

        // Idempotency: skip if already processed
        $existingNotification = PaymentNotification::where('order_id', $orderId)
            ->where('tran_id', $payload['tran_id'] ?? '')
            ->first();
        if ($existingNotification) {
            Log::info('Fiuu callback: duplicate notification ignored', ['order_id' => $orderId]);
            return response('OK');
        }

        DB::transaction(function () use ($payload, $paymentRequest, $orderId) {
            $status = $payload['status'] ?? 'failed';
            $tranId = $payload['tran_id'] ?? null;
            $channel = $payload['channel'] ?? null;

            PaymentNotification::create([
                'payment_request_id' => $paymentRequest->id,
                'order_id'           => $orderId,
                'tran_id'            => $tranId,
                'status'             => $status,
                'channel'            => $channel,
                'payload_json'       => json_encode($payload),
                'is_valid_signature' => true,
                'received_at'        => now(),
            ]);

            if ($status === 'success') {
                $paymentRequest->update([
                    'status'        => 'paid',
                    'fiuu_tran_id'  => $tranId,
                    'fiuu_channel'  => $channel,
                    'paid_at'       => now(),
                ]);

                $participant = $paymentRequest->participant;
                $participant->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);

                $this->audit->log('fiuu_payment_success', $participant->bill_id, [
                    'participant_id' => $participant->id,
                    'order_id'       => $orderId,
                    'tran_id'        => $tranId,
                ]);
            }
        });

        return response('OK');
    }

    public function return(Request $request)
    {
        $participantToken = $request->route('participantPayment');
        $participant = Participant::where('token', $participantToken)->firstOrFail();

        return Inertia::render('Payment/Return', [
            'participant' => [
                'name'   => $participant->name,
                'status' => $participant->status,
                'amount' => Money::format($participant->amount_cents),
            ],
        ]);
    }
}
