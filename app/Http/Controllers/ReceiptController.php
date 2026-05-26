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
        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        $request->validate(['receipt' => 'required|image|max:5120']);
        $path = $request->file('receipt')->store('receipts', 'public');
        $bill->update(['receipt_image_path' => $path]);
        $this->audit->log('receipt_uploaded', $bill);

        return back()->with('success', 'Receipt uploaded.');
    }

    public function parse(Bill $bill, Request $request): RedirectResponse
    {
        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        $request->validate(['ocr_text' => 'required|string']);
        $parsed = $this->parser->parse($request->input('ocr_text'));
        $bill->update([
            'ocr_raw_text' => $request->input('ocr_text'),
            'ocr_parsed_json' => $parsed,
            'ocr_confidence' => $parsed['confidence'],
            'merchant_name' => $parsed['merchant_name'],
            'receipt_date' => $parsed['date'],
            'subtotal_cents' => $parsed['subtotal_cents'],
            'tax_cents' => $parsed['tax_cents'],
            'service_charge_cents' => $parsed['service_charge_cents'],
            'rounding_cents' => $parsed['rounding_cents'],
            'total_cents' => $parsed['total_cents'],
        ]);
        $bill->items()->delete();
        foreach ($parsed['items'] as $idx => $item) {
            $bill->items()->create([
                'name' => $item['name'],
                'quantity' => $item['quantity'] ?? 1,
                'total_price_cents' => $item['total_cents'],
                'sort_order' => $idx,
                'source' => 'ocr',
            ]);
        }
        $this->audit->log('ocr_completed', $bill, metadata: ['confidence' => $parsed['confidence']]);

        return redirect()->route('bills.receipt.show', $bill);
    }

    public function saveItems(Bill $bill, Request $request): RedirectResponse
    {
        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        $data = $request->validate([
            'merchant_name' => 'nullable|string|max:120',
            'receipt_date' => 'nullable|date',
            'subtotal_cents' => 'required|integer|min:0',
            'tax_cents' => 'integer|min:0',
            'service_charge_cents' => 'integer|min:0',
            'rounding_cents' => 'integer|min:0',
            'total_cents' => 'required|integer|min:1',
            'items' => 'array',
            'items.*.name' => 'required|string|max:200',
            'items.*.quantity' => 'numeric|min:0',
            'items.*.total_price_cents' => 'required|integer|min:0',
        ]);

        $bill->update([
            'merchant_name' => $data['merchant_name'] ?? null,
            'receipt_date' => $data['receipt_date'] ?? null,
            'subtotal_cents' => $data['subtotal_cents'],
            'tax_cents' => $data['tax_cents'] ?? 0,
            'service_charge_cents' => $data['service_charge_cents'] ?? 0,
            'rounding_cents' => $data['rounding_cents'] ?? 0,
            'total_cents' => $data['total_cents'],
        ]);

        $bill->items()->delete();
        foreach ($data['items'] ?? [] as $idx => $item) {
            BillItem::create([
                'bill_id' => $bill->id,
                'name' => $item['name'],
                'quantity' => $item['quantity'] ?? 1,
                'total_price_cents' => $item['total_price_cents'],
                'sort_order' => $idx,
                'source' => 'manual',
            ]);
        }

        $this->audit->log('items_reviewed', $bill);

        return redirect()->route('bills.split.edit', $bill);
    }

    private function billPayload(Bill $bill): array
    {
        return [
            'public_token' => $bill->public_token,
            'title' => $bill->title,
            'merchant_name' => $bill->merchant_name,
            'receipt_date' => $bill->receipt_date?->format('Y-m-d'),
            'subtotal_cents' => $bill->subtotal_cents,
            'tax_cents' => $bill->tax_cents,
            'service_charge_cents' => $bill->service_charge_cents,
            'rounding_cents' => $bill->rounding_cents,
            'total_cents' => $bill->total_cents,
            'total' => Money::format($bill->total_cents),
            'ocr_confidence' => $bill->ocr_confidence,
            'ocr_raw_text' => $bill->ocr_raw_text,
        ];
    }
}
