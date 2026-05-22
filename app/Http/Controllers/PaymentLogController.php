<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

class PaymentLogController extends Controller
{
    public function index(Bill $bill): Response
    {
        $bill->load(['paymentRequests.participant', 'paymentRequests.notifications']);

        $logs = $bill->paymentRequests->flatMap(function ($pr) {
            return $pr->notifications->map(fn ($n) => [
                'order_id' => $n->order_id,
                'participant' => $pr->participant->name,
                'amount' => Money::format($pr->amount_cents),
                'status' => $n->status,
                'tran_id' => $n->tran_id,
                'signature_valid' => $n->is_valid_signature,
                'received_at' => $n->received_at->format('d M Y, g:i A'),
                'payload' => $n->payload_json,
            ]);
        })->merge($bill->paymentRequests->map(fn ($pr) => [
            'order_id' => $pr->order_id,
            'participant' => $pr->participant->name,
            'amount' => Money::format($pr->amount_cents),
            'status' => $pr->status,
            'tran_id' => $pr->fiuu_tran_id,
            'channel' => $pr->fiuu_channel,
            'signature_valid' => null,
            'received_at' => $pr->created_at->format('d M Y, g:i A'),
            'payload' => $pr->request_payload_json,
        ]))->values();

        return Inertia::render('Bills/Payments', [
            'bill' => ['public_token' => $bill->public_token, 'title' => $bill->title],
            'logs' => $logs,
        ]);
    }
}
