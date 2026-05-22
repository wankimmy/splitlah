<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Participant;
use App\Services\Audit\AuditLogService;
use App\Services\Payment\PaymentLinkService;
use App\Services\Split\SplitCalculatorService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function __construct(
        private AuditLogService $audit,
        private PaymentLinkService $links,
        private SplitCalculatorService $splitter,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Bills/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'organizer_name' => 'required|string|max:120',
            'organizer_email' => 'nullable|email',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'participants' => 'required|array|min:2',
            'participants.*.name' => 'required|string|max:120',
            'participants.*.phone' => 'nullable|string|max:30',
            'participants.*.email' => 'nullable|email',
        ]);

        $bill = Bill::create([
            'title' => $data['title'],
            'organizer_name' => $data['organizer_name'],
            'organizer_email' => $data['organizer_email'] ?? null,
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($data['participants'] as $p) {
            $bill->participants()->create([
                'name' => $p['name'],
                'phone' => $p['phone'] ?? null,
                'email' => $p['email'] ?? null,
            ]);
        }

        $this->audit->log('bill_created', $bill, actorName: $bill->organizer_name);

        return redirect()->route('bills.receipt.show', $bill);
    }

    public function show(Bill $bill, Request $request): Response
    {
        $bill->load(['participants', 'items', 'auditLogs']);
        $filter = $request->get('filter', 'all');
        $participants = $bill->participants->map(fn (Participant $p) => [
            'id' => $p->id,
            'token' => $p->token,
            'name' => $p->name,
            'amount_cents' => $p->amount_cents,
            'amount' => Money::format($p->amount_cents),
            'status' => $p->status,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'payment_url' => $this->links->paymentUrl($p),
            'whatsapp_url' => $this->links->whatsAppUrl($p),
            'share_message' => $this->links->shareMessage($p),
        ]);

        if ($filter !== 'all') {
            $participants = $participants->filter(fn ($p) => $p['status'] === $filter)->values();
        }

        $collected = $bill->collectedCents();

        return Inertia::render('Bills/Show', [
            'bill' => [
                'public_token' => $bill->public_token,
                'title' => $bill->title,
                'organizer_name' => $bill->organizer_name,
                'merchant_name' => $bill->merchant_name,
                'due_date' => $bill->due_date?->format('Y-m-d'),
                'status' => $bill->status,
                'total_cents' => $bill->total_cents,
                'total' => Money::format($bill->total_cents),
                'collected_cents' => $collected,
                'collected' => Money::format($collected),
                'remaining_cents' => max(0, $bill->total_cents - $collected),
                'remaining' => Money::format(max(0, $bill->total_cents - $collected)),
                'paid_count' => $bill->participants->whereIn('status', ['paid', 'manual_paid'])->count(),
                'participant_count' => $bill->participants->count(),
            ],
            'participants' => $participants,
            'filter' => $filter,
            'timeline' => $bill->auditLogs->take(20)->map(fn ($log) => [
                'action' => $log->action,
                'actor_name' => $log->actor_name,
                'created_at' => $log->created_at->format('d M Y, g:i A'),
                'metadata' => $log->metadata,
            ]),
            'summary_text' => $this->links->billSummary($bill),
        ]);
    }

    public function publish(Bill $bill): RedirectResponse
    {
        if ($bill->total_cents <= 0) {
            return back()->with('error', 'Set bill total before publishing.');
        }
        if ($bill->participants()->count() < 2) {
            return back()->with('error', 'Add at least 2 participants.');
        }
        if (! $this->splitter->validateTotal(
            $bill->participants->pluck('amount_cents')->all(),
            $bill->total_cents
        )) {
            return back()->with('error', 'Participant amounts must equal bill total.');
        }

        $bill->update(['status' => 'published', 'published_at' => now()]);
        $this->audit->log('bill_published', $bill, actorName: $bill->organizer_name);

        return redirect()->route('bills.show', $bill)->with('success', 'Bill published. Share payment links!');
    }

    public function summary(Bill $bill): \Illuminate\Http\JsonResponse
    {
        return response()->json(['text' => $this->links->billSummary($bill)]);
    }
}
