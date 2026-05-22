<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\ItemAssignment;
use App\Services\Audit\AuditLogService;
use App\Services\Split\SplitCalculatorService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SplitController extends Controller
{
    public function __construct(
        private SplitCalculatorService $splitter,
        private AuditLogService $audit,
    ) {}

    public function edit(Bill $bill): Response
    {
        $bill->load(['participants', 'items.assignments']);

        return Inertia::render('Bills/Split', [
            'bill' => [
                'public_token' => $bill->public_token,
                'title' => $bill->title,
                'total_cents' => $bill->total_cents,
                'total' => Money::format($bill->total_cents),
                'split_mode' => $bill->split_mode,
                'tax_distribution' => $bill->tax_distribution ?? 'proportional',
                'rounding_mode' => $bill->rounding_mode ?? 'exact',
            ],
            'participants' => $bill->participants->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'amount_cents' => $p->amount_cents,
                'percentage_share' => $p->percentage_share,
            ]),
            'items' => $bill->items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'total_price_cents' => $i->total_price_cents,
                'total' => Money::format($i->total_price_cents),
                'assigned_participant_ids' => $i->assignments->pluck('participant_id'),
            ]),
        ]);
    }

    public function update(Bill $bill, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'split_mode' => 'required|in:equal,manual,itemized,percentage',
            'tax_distribution' => 'nullable|in:equal,proportional',
            'rounding_mode' => 'nullable|in:exact,nearest_005,nearest_010,nearest_100',
            'manual_amounts' => 'array',
            'percentages' => 'array',
            'assignments' => 'array',
        ]);

        $bill->update([
            'split_mode' => $data['split_mode'],
            'tax_distribution' => $data['tax_distribution'] ?? 'proportional',
            'rounding_mode' => $data['rounding_mode'] ?? 'exact',
        ]);

        $participants = $bill->participants;
        $results = match ($data['split_mode']) {
            'equal' => $this->splitter->calculateEqual($bill, $participants),
            'manual' => $this->splitter->calculateManual($bill, $data['manual_amounts'] ?? []),
            'percentage' => $this->splitter->calculatePercentage($bill, $data['percentages'] ?? []),
            'itemized' => $this->splitter->calculateItemized($bill, $data['assignments'] ?? [], $bill->tax_distribution ?? 'proportional'),
            default => [],
        };

        if ($data['split_mode'] === 'manual' && ! $this->splitter->validateTotal(array_column($results, 'amount_cents'), $bill->total_cents)) {
            return back()->with('error', 'Manual amounts must equal bill total.');
        }

        if ($data['split_mode'] === 'percentage') {
            $pctSum = array_sum($data['percentages'] ?? []);
            if (abs($pctSum - 100) > 0.01) {
                return back()->with('error', 'Percentages must total 100%.');
            }
        }

        foreach ($results as $participantId => $row) {
            $bill->participants()->where('id', $participantId)->update([
                'amount_cents' => $row['amount_cents'],
                'subtotal_cents' => $row['subtotal_cents'],
                'tax_share_cents' => $row['tax_share_cents'],
                'service_charge_share_cents' => $row['service_charge_share_cents'],
                'rounding_share_cents' => $row['rounding_share_cents'],
                'breakdown_json' => $row['breakdown_json'],
            ]);
        }

        if ($data['split_mode'] === 'itemized' && ! empty($data['assignments'])) {
            ItemAssignment::whereIn('bill_item_id', $bill->items()->pluck('id'))->delete();
            foreach ($data['assignments'] as $itemId => $participantIds) {
                foreach ($participantIds as $pid) {
                    ItemAssignment::create([
                        'bill_item_id' => $itemId,
                        'participant_id' => $pid,
                    ]);
                }
            }
        }

        $this->audit->log('split_calculated', $bill, metadata: ['mode' => $data['split_mode']]);

        if ($request->boolean('publish')) {
            if ($bill->total_cents <= 0 || $bill->participants()->count() < 2) {
                return back()->with('error', 'Cannot publish: check total and participants.');
            }
            if (! $this->splitter->validateTotal($bill->participants->pluck('amount_cents')->all(), $bill->total_cents)) {
                return back()->with('error', 'Participant amounts must equal bill total.');
            }
            $bill->update(['status' => 'published', 'published_at' => now()]);
            $this->audit->log('bill_published', $bill, actorName: $bill->organizer_name);

            return redirect()->route('bills.show', $bill)->with('success', 'Bill published!');
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Split saved.');
    }
}
