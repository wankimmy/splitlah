<?php

namespace App\Http\Controllers;

use App\Actions\BillStoreAction;
use App\Http\Requests\StoreBillRequest;
use App\Models\Bill;
use App\Services\Audit\AuditLogService;
use App\Services\Payment\PaymentLinkService;
use App\Services\Split\SplitCalculatorService;
use Illuminate\Http\RedirectResponse;
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

    public function store(StoreBillRequest $request, BillStoreAction $action): RedirectResponse
    {
        $bill = $action->execute($request->validated());
        return redirect()->route('bills.show', $bill);
    }

    public function show(Bill $bill): Response
    {
        $this->authorizeOrganizer($bill);

        $bill->load(['participants', 'items.assignments']);

        return Inertia::render('Bills/Show', [
            'bill' => $this->billPayload($bill),
            'participants' => $bill->participants->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'amount_cents' => $p->amount_cents,
                'amount' => \App\Support\Money::format($p->amount_cents),
                'status' => $p->status,
                'token' => $p->token,
            ]),
        ]);
    }

    public function edit(Bill $bill): Response
    {
        $this->authorizeOrganizer($bill);

        return Inertia::render('Bills/Edit', [
            'bill' => $this->billPayload($bill),
        ]);
    }

    public function update(StoreBillRequest $request, Bill $bill): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        $bill->update($request->validated());
        $this->audit->log('bill_updated', $bill);

        return redirect()->route('bills.show', $bill)->with('success', 'Bill updated.');
    }

    public function destroy(Bill $bill): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        $bill->delete();
        $this->audit->log('bill_deleted', $bill);

        return redirect()->route('home')->with('success', 'Bill deleted.');
    }

    public function publish(Bill $bill): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($bill->status !== 'draft') {
            return back()->with('error', 'Bill is already published.');
        }

        $bill->update(['status' => 'published']);
        $this->audit->log('bill_published', $bill);

        return redirect()->route('bills.summary', $bill);
    }

    public function summary(Bill $bill): Response
    {
        $this->authorizeOrganizer($bill);

        $bill->load(['participants', 'items']);

        return Inertia::render('Bills/Summary', [
            'bill' => $this->billPayload($bill),
            'participants' => $bill->participants->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'amount_cents' => $p->amount_cents,
                'amount' => \App\Support\Money::format($p->amount_cents),
                'status' => $p->status,
                'token' => $p->token,
            ]),
        ]);
    }

    protected function authorizeOrganizer(Bill $bill): void
    {
        $sessionToken = session('organizer_token');
        if (!$sessionToken || !hash_equals($bill->organizer_token, hash('sha256', $sessionToken))) {
            abort(403, 'Unauthorized');
        }
    }

    protected function billPayload(Bill $bill): array
    {
        return [
            'public_token' => $bill->public_token,
            'title' => $bill->title,
            'total_cents' => $bill->total_cents,
            'total' => \App\Support\Money::format($bill->total_cents),
            'status' => $bill->status,
            'split_mode' => $bill->split_mode,
        ];
    }
}