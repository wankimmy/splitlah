<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsPublishedBill;
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
    use GuardsPublishedBill;
    public function __construct(
        private SplitCalculatorService $splitter,
        private AuditLogService $audit,
    ) {}

    public function edit(Bill $bill): Response|RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

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
                'amount' => Money::format($p->amount_cents),
                'status' => $p->status,
                'token' => $p->token,
            ]),
            'items' => $bill->items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'quantity' => (float) $i->quantity,
                'unit_price_cents' => $i->unit_price_cents,
                'total_price_cents' => $i->total_price_cents,
                'total' => Money::format($i->total_price_cents),
                'is_fee' => $i->is_fee,
            ]),
        ]);
    }

    public function update(Bill $bill, Request $request): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        $validated = $request->validate([
            'participants' => 'required|array',
            'participants.*.id' => 'required|integer|exists:participants,id',
            'participants.*.amount_cents' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($bill, $validated) {
            foreach ($validated['participants'] as $p) {
                Participant::where('id', $p['id'])->where('bill_id', $bill->id)->update([
                    'amount_cents' => $p['amount_cents'],
                ]);
            }
            $this->audit->log('splits_updated', $bill);
        });

        return redirect()->route('bills.splits.edit', $bill)->with('success', 'Splits updated.');
    }

    protected function authorizeOrganizer(Bill $bill): void
    {
        $sessionToken = session('organizer_token');
        if (!$sessionToken || !hash_equals($bill->organizer_token, hash('sha256', $sessionToken))) {
            abort(403, 'Unauthorized');
        }
    }
}
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

        if ($data['split_mode'] === 'itemized' && ! empty($data['assignments'])) {
            $allowedItemIds = $bill->items()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $allowedParticipantIds = $bill->participants()->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($data['assignments'] as $itemId => $participantIds) {
                if (! in_array((int) $itemId, $allowedItemIds, true)) {
                    return back()->with('error', 'Invalid item assignment.');
                }
                foreach ($participantIds as $pid) {
                    if (! in_array((int) $pid, $allowedParticipantIds, true)) {
                        return back()->with('error', 'Invalid participant assignment.');
                    }
                }
            }
        }

        foreach ($results as $participantId => $row) {
            $update = [
                'amount_cents' => $row['amount_cents'],
                'subtotal_cents' => $row['subtotal_cents'],
                'tax_share_cents' => $row['tax_share_cents'],
                'service_charge_share_cents' => $row['service_charge_share_cents'],
                'rounding_share_cents' => $row['rounding_share_cents'],
                'breakdown_json' => $row['breakdown_json'],
            ];
            if ($data['split_mode'] === 'percentage') {
                $update['percentage_share'] = $data['percentages'][$participantId] ?? null;
            }
            $bill->participants()->where('id', $participantId)->update($update);
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

    protected function authorizeOrganizer(Bill $bill): void
    {
        $sessionToken = session('organizer_token');
        if (!$sessionToken || !hash_equals($bill->organizer_token, hash('sha256', $sessionToken))) {
            abort(403, 'Unauthorized');
        }
    }
}