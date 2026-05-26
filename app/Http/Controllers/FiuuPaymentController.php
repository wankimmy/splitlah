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

        $action = $this->fiuu->paymentActionUrl();
        $fields = '';
        foreach ($payload as $key => $value) {
            $fields .= '<input type="hidden" name="'.e($key).'" value="'.e($value).'">';
        }

        $html = '<!DOCTYPE html><html><body onload="document.forms[0].submit()">'
            .'<form method="POST" action="'.e($action).'">'.$fields
            .'<p>Redirecting to Fiuu...</p></form></body></html>';

        return response($html)->header('Content-Type', 'text/html');
    }

    public function return(Request $request)
    {
        $orderId = $request->input('orderid');
        $paymentRequest = PaymentRequest::where('order_id', $orderId)->first();

        return Inertia::render('Payments/Return', [
            'order_id' => $orderId,
            'status' => $paymentRequest?->status ?? 'pending',
            'message' => 'Payment submitted. We are waiting for confirmation from Fiuu.',
        ]);
    }

    public function cancel(Request $request)
    {
        return Inertia::render('Payments/Cancel', [
            'order_id' => $request->input('orderid'),
        ]);
    }

    public function notify(Request $request): Response
    {
        $payload = $request->all();
        $valid = $this->fiuu->verifySkey($payload);
        $orderId = (string) ($payload['orderid'] ?? '');
        $paymentRequest = PaymentRequest::where('order_id', $orderId)->first();

        PaymentNotification::create([
            'payment_request_id' => $paymentRequest?->id,
            'order_id' => $orderId,
            'tran_id' => $payload['tranID'] ?? null,
            'status' => $payload['status'] ?? null,
            'is_valid_signature' => $valid,
            'payload_json' => $payload,
            'received_at' => now(),
        ]);

        if (! $paymentRequest || ! $valid) {
            return response('OK');
        }

        $expectedAmount = Money::toDecimal($paymentRequest->amount_cents);
        $expectedCurrency = strtoupper($paymentRequest->currency ?? 'MYR');
        if (($payload['amount'] ?? '') !== $expectedAmount
            || strtoupper((string) ($payload['currency'] ?? '')) !== $expectedCurrency) {
            $this->audit->log('fiuu_amount_mismatch', $paymentRequest->bill, $paymentRequest->participant, $paymentRequest, metadata: [
                'expected_amount' => $expectedAmount,
                'received_amount' => $payload['amount'] ?? null,
                'expected_currency' => $expectedCurrency,
                'received_currency' => $payload['currency'] ?? null,
            ]);

            return response('OK');
        }

        $mapped = $this->fiuu->mapStatus((string) ($payload['status'] ?? ''));

        DB::transaction(function () use ($paymentRequest, $payload, $mapped) {
            $paymentRequest->refresh();
            if ($paymentRequest->status === 'paid') {
                return;
            }

            $paymentRequest->update([
                'response_payload_json' => $payload,
                'fiuu_tran_id' => $payload['tranID'] ?? null,
                'fiuu_channel' => $payload['channel'] ?? null,
                'fiuu_appcode' => $payload['appcode'] ?? null,
                'fiuu_paydate' => $payload['paydate'] ?? null,
            ]);

            $participant = $paymentRequest->participant;

            if ($mapped === 'paid') {
                $paymentRequest->update(['status' => 'paid', 'paid_at' => now()]);
                $participant->update(['status' => 'paid', 'paid_at' => now()]);
                $this->audit->log('payment_paid', $paymentRequest->bill, $participant, $paymentRequest, metadata: ['provider' => 'fiuu']);
            } elseif ($mapped === 'failed') {
                $paymentRequest->update(['status' => 'failed']);
                $participant->update(['status' => 'failed']);
                $this->audit->log('payment_failed', $paymentRequest->bill, $participant, $paymentRequest);
            } else {
                $paymentRequest->update(['status' => 'pending']);
                $participant->update(['status' => 'pending']);
            }

            $this->audit->log('fiuu_notify_received', $paymentRequest->bill, $participant, $paymentRequest);
        });

        return response('OK');
    }
}
