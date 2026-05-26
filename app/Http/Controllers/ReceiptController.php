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
            'receipt' => 'required|file|mimes:jpeg,png,webp|max:10240',
        ]);

        $path = $request->file('receipt')->store('receipts', 'public');
        $bill->update(['receipt_image_path' => $path]);
        $this->audit->log('receipt_uploaded', $bill);

        return back()->with('success', 'Receipt uploaded.');
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
        $bill->items()->delete();
        foreach ($items as $item) {
            BillItem::create([
                'bill_id' => $bill->id,
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price_cents' => $item['unit_price_cents'],
                'total_price_cents' => $item['total_price_cents'],
                'is_fee' => $item['is_fee'] ?? false,
            ]);
        }
        $this->audit->log('receipt_parsed', $bill);

        return redirect()->route('bills.receipt.show', $bill)->with('success', 'Receipt parsed.');
    }

    public function saveItems(Bill $bill, Request $request): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price_cents' => 'required|integer|min:0',
            'items.*.is_fee' => 'boolean',
        ]);

        $bill->items()->delete();
        foreach ($validated['items'] as $item) {
            BillItem::create([
                'bill_id' => $bill->id,
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price_cents' => $item['unit_price_cents'],
                'total_price_cents' => (int) round($item['quantity'] * $item['unit_price_cents']),
                'is_fee' => $item['is_fee'] ?? false,
            ]);
        }
        $this->audit->log('receipt_items_saved', $bill);

        return redirect()->route('bills.receipt.show', $bill)->with('success', 'Items saved.');
    }

    protected function billPayload(Bill $bill): array
    {
        return [
            'public_token' => $bill->public_token,
            'title' => $bill->title,
            'total_cents' => $bill->total_cents,
            'total' => Money::format($bill->total_cents),
            'status' => $bill->status,
            'split_mode' => $bill->split_mode,
        ];
    }

    protected function authorizeOrganizer(Bill $bill): void
    {
        $sessionToken = session('organizer_token');
        if (!$sessionToken || !hash_equals($bill->organizer_token, hash('sha256', $sessionToken))) {
            abort(403, 'Unauthorized');
        }
    }
}
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

    protected function authorizeOrganizer(Bill $bill): void
    {
        $sessionToken = session('organizer_token');
        if (!$sessionToken || !hash_equals($bill->organizer_token, hash('sha256', $sessionToken))) {
            abort(403, 'Unauthorized');
        }
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