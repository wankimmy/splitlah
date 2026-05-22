<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Services\Audit\AuditLogService;
use App\Services\Fiuu\FiuuGateway;
use App\Services\Payment\PaymentLinkService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantPaymentController extends Controller
{
    public function __construct(
        private PaymentLinkService $links,
        private AuditLogService $audit,
        private FiuuGateway $fiuu,
    ) {}

    public function show(string $token, Request $request): Response
    {
        $participant = Participant::where('token', $token)->with('bill')->firstOrFail();
        $participant->update(['last_opened_at' => now()]);
        $this->audit->log('payment_link_opened', $participant->bill, $participant);

        return Inertia::render('Pay/Show', [
            'participant' => [
                'token' => $participant->token,
                'name' => $participant->name,
                'amount_cents' => $participant->amount_cents,
                'amount' => Money::format($participant->amount_cents),
                'status' => $participant->status,
                'breakdown' => $participant->breakdown_json,
                'is_paid' => $participant->isPaid(),
            ],
            'bill' => [
                'title' => $participant->bill->title,
                'organizer_name' => $participant->bill->organizer_name,
                'due_date' => $participant->bill->due_date?->format('d M Y'),
            ],
            'payment_url' => $this->links->paymentUrl($participant),
            'qr_value' => $this->links->qrValue($participant),
            'fiuu_enabled' => $this->fiuu->isEnabled(),
            'duitnow_configured' => (bool) config('services.fiuu.duitnow_channel'),
            'fiuu_create_url' => route('payments.fiuu.create', $participant->token),
        ]);
    }
}
