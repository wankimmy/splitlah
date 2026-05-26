<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\PaymentRequest;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

class PaymentLogController extends Controller
{
    public function index(Bill $bill): Response
    {
        $bill->load(['paymentRequests.participant']);

        $paymentRequests = $bill->paymentRequests()
            ->with(['notifications', 'participant'])
            ->paginate(15);

        $logs = $paymentRequests->through(function ($pr) {
            $notifications = $pr->notifications->map(fn ($n) => [
                'order_id' => $n->order_id,
                'participant' => $pr->participant->name,
                'amount' => Money::format($pr->amount_cents),
                'status' => $n->status,
                'tran_id' => $n->tran_id,
                'channel' => null,
                'signature_valid' => $n->is_valid_signature,
                'received_at' => $n->received_at->format('d M Y, g:i A'),
            ]);

            $requestLog = [
                'order_id' => $pr->order_id,
                'participant' => $pr->participant->name,
                'amount' => Money::format($pr->amount_cents),
                'status' => $pr->status,
                'tran_id' => $pr->fiuu_tran_id,
                'channel' => $pr->fiuu_channel,
                'signature_valid' => null,
                'received_at' => $pr->created_at->format('d M Y, g:i A'),
            ];

            return $notifications->isEmpty() ? [$requestLog] : $notifications;
        })->flatten(1);

        return Inertia::render('Bills/Payments', [
            'bill' => ['public_token' => $bill->public_token, 'title' => $bill->title],
            'logs' => $logs,
            'paginator' => $paymentRequests->toArray(),
        ]);
    }
}