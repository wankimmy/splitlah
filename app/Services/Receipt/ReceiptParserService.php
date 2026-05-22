<?php

namespace App\Services\Receipt;

use App\Support\Money;

class ReceiptParserService
{
    private const TOTAL_LABELS = [
        'TOTAL', 'GRAND TOTAL', 'AMOUNT DUE', 'JUMLAH', 'JUMLAH BESAR', 'NET TOTAL', 'BALANCE DUE',
    ];

    private const NOISE = [
        'CASH', 'CHANGE', 'RECEIPT', 'TAX INVOICE', 'SST', 'THANK YOU', 'TERIMA KASIH',
    ];

    public function parse(string $rawText): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawText))));
        $merchant = $this->detectMerchant($lines);
        $date = $this->detectDate($rawText);
        $amounts = $this->extractAmounts($lines);
        $totalCents = $this->detectTotalCents($lines, $amounts);
        $taxCents = $this->detectFeeCents($lines, ['SST', 'TAX', 'GST']);
        $serviceCents = $this->detectFeeCents($lines, ['SERVICE CHARGE', 'SERVICE FEE']);
        $roundingCents = $this->detectFeeCents($lines, ['ROUNDING', 'ROUND']);
        $items = $this->detectItems($lines, $totalCents, $taxCents, $serviceCents, $roundingCents);
        $itemsTotal = array_sum(array_column($items, 'total_cents'));
        $subtotalCents = $itemsTotal > 0 ? $itemsTotal : max(0, $totalCents - $taxCents - $serviceCents - $roundingCents);
        $confidence = $this->confidence($totalCents, $items, $merchant);
        $warnings = [];
        if ($totalCents <= 0) {
            $warnings[] = 'Could not detect total. Please enter manually.';
        }

        return [
            'merchant_name' => $merchant,
            'date' => $date,
            'subtotal_cents' => $subtotalCents,
            'tax_cents' => $taxCents,
            'service_charge_cents' => $serviceCents,
            'rounding_cents' => $roundingCents,
            'total_cents' => max($totalCents, 0),
            'items' => $items,
            'confidence' => $confidence,
            'warnings' => $warnings,
        ];
    }

    private function detectMerchant(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 5) as $line) {
            if ($this->isNoise($line) || preg_match('/^\d/', $line)) {
                continue;
            }
            if (strlen($line) >= 3 && strlen($line) <= 80) {
                return $line;
            }
        }

        return null;
    }

    private function detectDate(string $text): ?string
    {
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $text, $m)) {
            $y = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

            return sprintf('%04d-%02d-%02d', (int) $y, (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function extractAmounts(array $lines): array
    {
        $amounts = [];
        foreach ($lines as $i => $line) {
            if (preg_match_all('/(?:RM|MYR)?\s*(\d+\.\d{2})/i', $line, $matches)) {
                foreach ($matches[1] as $amt) {
                    $amounts[] = ['line' => $i, 'cents' => Money::fromDecimal($amt), 'text' => $line];
                }
            }
        }

        return $amounts;
    }

    private function detectTotalCents(array $lines, array $amounts): int
    {
        foreach ($lines as $line) {
            $upper = strtoupper($line);
            foreach (self::TOTAL_LABELS as $label) {
                if (str_contains($upper, $label) && preg_match('/(?:RM|MYR)?\s*(\d+\.\d{2})/i', $line, $m)) {
                    return Money::fromDecimal($m[1]);
                }
            }
        }
        if (count($amounts) > 0) {
            return max(array_column($amounts, 'cents'));
        }

        return 0;
    }

    private function detectFeeCents(array $lines, array $keywords): int
    {
        foreach ($lines as $line) {
            $upper = strtoupper($line);
            foreach ($keywords as $kw) {
                if (str_contains($upper, $kw) && preg_match('/(\d+\.\d{2})/', $line, $m)) {
                    return Money::fromDecimal($m[1]);
                }
            }
        }

        return 0;
    }

    private function detectItems(array $lines, int $total, int $tax, int $service, int $rounding): array
    {
        $items = [];
        $feeKeywords = ['SST', 'TAX', 'SERVICE', 'ROUND', 'TOTAL', 'SUBTOTAL', 'JUMLAH'];
        foreach ($lines as $line) {
            $upper = strtoupper($line);
            if ($this->isNoise($line)) {
                continue;
            }
            foreach ($feeKeywords as $kw) {
                if (str_contains($upper, $kw)) {
                    continue 2;
                }
            }
            if (preg_match('/^(.+?)\s+(\d+\.\d{2})\s*$/i', $line, $m)) {
                $items[] = [
                    'name' => trim($m[1]),
                    'quantity' => 1,
                    'total_cents' => Money::fromDecimal($m[2]),
                ];
            } elseif (preg_match('/^(.+?)\s+x\s*(\d+)\s+(\d+\.\d{2})/i', $line, $m)) {
                $items[] = [
                    'name' => trim($m[1]),
                    'quantity' => (float) $m[2],
                    'total_cents' => Money::fromDecimal($m[3]),
                ];
            }
        }

        return $items;
    }

    private function isNoise(string $line): bool
    {
        $upper = strtoupper($line);
        foreach (self::NOISE as $n) {
            if ($upper === $n || str_starts_with($upper, $n.' ')) {
                return true;
            }
        }
        if (preg_match('/^(\+?6?0)?1\d{8,9}$/', preg_replace('/\D/', '', $line))) {
            return true;
        }

        return false;
    }

    private function confidence(int $total, array $items, ?string $merchant): string
    {
        $score = 0;
        if ($total > 0) {
            $score += 2;
        }
        if (count($items) >= 2) {
            $score += 2;
        }
        if ($merchant) {
            $score += 1;
        }

        return match (true) {
            $score >= 4 => 'high',
            $score >= 2 => 'medium',
            default => 'low',
        };
    }
}
