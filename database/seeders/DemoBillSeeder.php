<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Participant;
use App\Services\Split\SplitCalculatorService;
use Illuminate\Database\Seeder;

class DemoBillSeeder extends Seeder
{
    public function run(): void
    {
        $ocr = <<<'TEXT'
RESTORAN NASI KANDAR MAJU
21/05/2026
NASI KANDAR AYAM MADU 18.90
NASI KANDAR SOTONG 22.50
TEH AIS x4 12.00
ROTI TISSUE 9.00
SERVICE CHARGE 8.00
SST 5.00
TOTAL RM86.40
THANK YOU
TEXT;

        $bill = Bill::create([
            'title' => 'Friday Nasi Kandar Lunch',
            'organizer_name' => 'Safwan',
            'merchant_name' => 'Restoran Nasi Kandar Maju',
            'receipt_date' => '2026-05-21',
            'subtotal_cents' => 7340,
            'tax_cents' => 500,
            'service_charge_cents' => 800,
            'rounding_cents' => 0,
            'total_cents' => 8640,
            'split_mode' => 'equal',
            'status' => 'published',
            'published_at' => now(),
            'ocr_raw_text' => $ocr,
            'ocr_confidence' => 'high',
        ]);

        $items = [
            ['name' => 'Nasi Kandar Ayam Madu', 'cents' => 1890, 'fee' => false],
            ['name' => 'Nasi Kandar Sotong', 'cents' => 2250, 'fee' => false],
            ['name' => 'Teh Ais x4', 'cents' => 1200, 'fee' => false],
            ['name' => 'Roti Tissue', 'cents' => 900, 'fee' => false],
            ['name' => 'Service Charge', 'cents' => 800, 'fee' => true],
            ['name' => 'SST', 'cents' => 500, 'fee' => true],
        ];
        foreach ($items as $i => $item) {
            BillItem::create([
                'bill_id' => $bill->id,
                'name' => $item['name'],
                'quantity' => 1,
                'total_price_cents' => $item['cents'],
                'sort_order' => $i,
                'source' => 'system',
                'is_fee' => $item['fee'],
            ]);
        }

        $names = ['Ali', 'Mei Ling', 'Kumar', 'Aisyah'];
        $participants = [];
        foreach ($names as $name) {
            $participants[] = Participant::create([
                'bill_id' => $bill->id,
                'name' => $name,
            ]);
        }

        $splitter = app(SplitCalculatorService::class);
        $results = $splitter->calculateEqual($bill, collect($participants));
        foreach ($results as $pid => $row) {
            Participant::where('id', $pid)->update([
                'amount_cents' => $row['amount_cents'],
                'subtotal_cents' => $row['subtotal_cents'],
                'breakdown_json' => $row['breakdown_json'],
            ]);
        }

        Participant::where('name', 'Ali')->update(['status' => 'paid', 'paid_at' => now()->subHours(2)]);
        Participant::where('name', 'Kumar')->update(['status' => 'pending']);
    }
}
