<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsPublishedBill;
use App\Models\Bill;
use App\Models\BillItem;
use App\Services\Audit\AuditLogService;
use App\Services\Receipt\ReceiptParserService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    use GuardsPublishedBill;

    public function __construct(
        private ReceiptParserService $parser,
        private AuditLogService $audit,
    ) {}

    public function show(Bill $bill): Response
    {
        $this->authorizeOrganizer($bill);

        $bill->load('items');

        return Inertia::render('Bills/Receipt', [
            'bill' => $this->billPayload($bill),
            'items' => $bill->items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'quantity' => (float) $i->quantity,
                'unit_price_cents' => $i->unit_price_cents,
                'total_price_cents' => $i->total_price_cents,
                'total' => Money::format($i->total_price_cents),
                'is_fee' => $i->is_fee,
            ]),
            'receipt_url' => $bill->receipt_image_path ? asset('storage/'.$bill->receipt_image_path) : null,
        ]);
    }

    public function upload(Bill $bill, Request $request): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        $request->validate([
            'receipt' => [
                'required',
                'file',
                'mimes:jpeg,png,webp',
                'max:10240', // 10 MB
            ],
        ]);

        $file = $request->file('receipt');
        $path = $file->store('receipts', 'public');

        $bill->update(['receipt_image_path' => $path]);

        $this->audit->log('receipt_uploaded', $bill, ['path' => $path]);

        return redirect()->route('receipt.show', $bill)->with('success', 'Receipt uploaded.');
    }

    public function parse(Bill $bill): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        if (!$bill->receipt_image_path) {
            return back()->with('error', 'No receipt to parse.');
        }

        $items = $this->parser->parse($bill->receipt_image_path);

        foreach ($items as $item) {
            BillItem::create([
                'bill_id' => $bill->id,
                'name' => $item['name'],
                'quantity' => $item['quantity'] ?? 1,
                'unit_price_cents' => $item['unit_price_cents'],
                'total_price_cents' => $item['total_price_cents'],
                'is_fee' => $item['is_fee'] ?? false,
            ]);
        }

        $this->audit->log('receipt_parsed', $bill, ['item_count' => count($items)]);

        return redirect()->route('receipt.show', $bill)->with('success', 'Receipt parsed.');
    }

    protected function billPayload(Bill $bill): array
    {
        return [
            'public_token' => $bill->public_token,
            'title' => $bill->title,
            'total_cents' => $bill->total_cents,
            'total' => Money::format($bill->total_cents),
            'split_mode' => $bill->split_mode,
            'tax_distribution' => $bill->tax_distribution ?? 'proportional',
            'rounding_mode' => $bill->rounding_mode ?? 'exact',
        ];
    }
}