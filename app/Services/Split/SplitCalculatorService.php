<?php

namespace App\Services\Split;

use App\Models\Bill;
use Illuminate\Support\Collection;

class SplitCalculatorService
{
    public function calculateEqual(Bill $bill, Collection $participants): array
    {
        $count = max(1, $participants->count());
        $base = intdiv($bill->total_cents, $count);
        $remainder = $bill->total_cents - ($base * $count);
        $results = [];
        foreach ($participants->values() as $i => $participant) {
            $extra = $i < $remainder ? 1 : 0;
            $amount = $base + $extra;
            $results[$participant->id] = $this->buildBreakdown($bill, $amount, $amount, 0, 0, 0);
        }

        return $this->applyRoundingMode($results, $bill);
    }

    public function calculateManual(Bill $bill, array $participantAmounts): array
    {
        $results = [];
        foreach ($participantAmounts as $participantId => $cents) {
            $cents = (int) $cents;
            $results[$participantId] = $this->buildBreakdown($bill, $cents, $cents, 0, 0, 0);
        }

        return $this->applyRoundingMode($results, $bill);
    }

    public function calculatePercentage(Bill $bill, array $percentages): array
    {
        $results = [];
        $assigned = 0;
        $ids = array_keys($percentages);
        $lastId = end($ids);
        foreach ($percentages as $participantId => $pct) {
            if ($participantId === $lastId) {
                $amount = $bill->total_cents - $assigned;
            } else {
                $amount = (int) floor($bill->total_cents * ((float) $pct) / 100);
                $assigned += $amount;
            }
            $results[$participantId] = $this->buildBreakdown($bill, $amount, $amount, 0, 0, 0);
        }

        return $this->applyRoundingMode($results, $bill);
    }

    public function calculateItemized(Bill $bill, array $assignments, string $taxDistribution = 'proportional'): array
    {
        $bill->load(['items', 'participants']);
        $participantTotals = [];
        foreach ($bill->participants as $p) {
            $participantTotals[$p->id] = ['subtotal' => 0, 'items' => []];
        }
        foreach ($bill->items as $item) {
            if ($item->is_fee) {
                continue;
            }
            $assignedIds = $assignments[$item->id] ?? [];
            if (empty($assignedIds)) {
                continue;
            }
            $share = intdiv($item->total_price_cents, count($assignedIds));
            $rem = $item->total_price_cents - ($share * count($assignedIds));
            foreach (array_values($assignedIds) as $idx => $pid) {
                $cents = $share + ($idx < $rem ? 1 : 0);
                $participantTotals[$pid]['subtotal'] += $cents;
                $participantTotals[$pid]['items'][] = ['name' => $item->name, 'cents' => $cents];
            }
        }
        $subtotalSum = array_sum(array_column($participantTotals, 'subtotal'));
        if ($subtotalSum === 0) {
            return $this->calculateEqual($bill, $bill->participants);
        }
        $extra = $bill->tax_cents + $bill->service_charge_cents + $bill->rounding_cents;
        $results = [];
        $extraAssigned = 0;
        $pIds = array_keys($participantTotals);
        $lastId = end($pIds);
        foreach ($participantTotals as $pid => $data) {
            $ratio = $data['subtotal'] / $subtotalSum;
            if ($taxDistribution === 'equal') {
                $taxShare = intdiv($bill->tax_cents, count($participantTotals));
                $svcShare = intdiv($bill->service_charge_cents, count($participantTotals));
                $roundShare = intdiv($bill->rounding_cents, count($participantTotals));
            } else {
                $taxShare = (int) round($bill->tax_cents * $ratio);
                $svcShare = (int) round($bill->service_charge_cents * $ratio);
                $roundShare = (int) round($bill->rounding_cents * $ratio);
            }
            if ($pid === $lastId) {
                $amount = $bill->total_cents - $extraAssigned;
            } else {
                $amount = $data['subtotal'] + $taxShare + $svcShare + $roundShare;
                $extraAssigned += $amount;
            }
            $results[$pid] = $this->buildBreakdown($bill, $amount, $data['subtotal'], $taxShare, $svcShare, $roundShare, $data['items']);
        }

        return $this->applyRoundingMode($results, $bill);
    }

    public function validateTotal(array $amounts, int $expectedTotalCents): bool
    {
        return array_sum($amounts) === $expectedTotalCents;
    }

    public function applyRounding(array $amounts, string $roundingMode, int $expectedTotalCents): array
    {
        if ($roundingMode === 'exact') {
            return $amounts;
        }
        $step = match ($roundingMode) {
            'nearest_005' => 5,
            'nearest_010' => 10,
            'nearest_100' => 100,
            default => 1,
        };
        $rounded = [];
        foreach ($amounts as $id => $cents) {
            $rounded[$id] = (int) (round($cents / $step) * $step);
        }
        $diff = $expectedTotalCents - array_sum($rounded);
        if ($diff !== 0 && count($rounded) > 0) {
            $firstKey = array_key_first($rounded);
            $rounded[$firstKey] += $diff;
        }

        return $rounded;
    }

    private function applyRoundingMode(array $results, Bill $bill): array
    {
        $amounts = [];
        foreach ($results as $pid => $data) {
            $amounts[$pid] = $data['amount_cents'];
        }
        $rounded = $this->applyRounding($amounts, $bill->rounding_mode ?? 'exact', $bill->total_cents);
        foreach ($rounded as $pid => $cents) {
            $results[$pid]['amount_cents'] = $cents;
        }

        return $results;
    }

    private function buildBreakdown(Bill $bill, int $amount, int $subtotal, int $tax, int $service, int $rounding, array $items = []): array
    {
        return [
            'amount_cents' => $amount,
            'subtotal_cents' => $subtotal,
            'tax_share_cents' => $tax,
            'service_charge_share_cents' => $service,
            'rounding_share_cents' => $rounding,
            'breakdown_json' => [
                'bill_title' => $bill->title,
                'items' => $items,
            ],
        ];
    }
}
