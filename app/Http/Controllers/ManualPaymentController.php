<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Participant;
use App\Models\PaymentRequest;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualPaymentController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function store(Bill $bill, Participant $participant, Request $request): RedirectResponse
    {
        if ($participant->bill_id !== $bill->id) {
            abort(404);
        }

        if ($participant->isPaid()) {
            return back()->with('error', 'Participant already paid.');
        }

        $data = $request->validate([
            'method' => 'required|in:cash,duitnow,bank_transfer,other',
            'reference_no' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        $paymentRequest = PaymentRequest::create([
            'participant_id' => $participant->id,
            'bill_id' => $participant->bill_id,
            'order_id' => 'MAN'.now()->format('YmdHis').substr($participant->token, 0, 6),
            'provider' => 'manual',
            'amount_cents' => $participant->amount_cents,
            'status' => 'paid',
            'paid_at' => now(),
            'request_payload_json' => $data,
        ]);

        $participant->update(['status' => 'manual_paid', 'paid_at' => now()]);
        $this->audit->log('manual_mark_paid', $participant->bill, $participant, $paymentRequest, metadata: $data);

        return back()->with('success', 'Marked as paid manually.');
    }
}
