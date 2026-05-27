<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceiptRequest;
use App\Models\Bill;
use App\Models\BillItem;
use App\Services\Audit\AuditLogService;
use App\Services\Receipt\ReceiptParserService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReceiptController extends Controller
{
    use GuardsPublishedBill;

    public function __construct(
        private ReceiptParserService $parser,
        private AuditLogService $audit,
    ) {}

    public function show(Bill $bill): InertiaResponse
    {
        $this->authorizeOrganizer($bill);

        $bill->load('items');

        $receiptUrl = null;
        if ($bill->receipt_image_path) {
            $receiptUrl = route('receipts.image', ['bill' => $bill->id]);
        }

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
            'receipt_url' => $receiptUrl,
        ]);
    }

    public function store(StoreReceiptRequest $request, Bill $bill): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        $file = $request->file('receipt');

        // Use guessExtension() for safer extension detection
        $extension = $file->guessExtension();
        $filename = Str::uuid() . '.' . $extension;

        // Delete old receipt if exists
        if ($bill->receipt_image_path) {
            Storage::disk('local')->delete($bill->receipt_image_path);
        }

        // Store in storage/app/receipts (local disk, not publicly accessible)
        $path = $file->storeAs('receipts', $filename, 'local');

        // Update bill record
        $bill->update(['receipt_image_path' => $path]);

        $this->audit->log('receipt_uploaded', [
            'bill_id' => $bill->id,
            'path' => $path,
        ]);

        return redirect()->route('bills.receipt', $bill)
            ->with('success', 'Receipt uploaded successfully.');
    }

    public function image(Bill $bill): Response
    {
        $this->authorizeOrganizer($bill);

        if (!$bill->receipt_image_path) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($bill->receipt_image_path)) {
            abort(404);
        }

        return response()->file($disk->path($bill->receipt_image_path), [
            'Content-Type' => $disk->mimeType($bill->receipt_image_path),
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function parse(Bill $bill): RedirectResponse
    {
        $this->authorizeOrganizer($bill);

        if ($redirect = $this->rejectIfPublished($bill)) {
            return $redirect;
        }

        if (!$bill->receipt_image_path) {
            return back()->with('error', 'No receipt uploaded.');
        }

        $fullPath = Storage::disk('local')->path($bill->receipt_image_path);

        if (!file_exists($fullPath)) {
            return back()->with('error', 'Receipt file not found.');
        }

        $items = $this->parser->parse($fullPath);

        if (empty($items)) {
            return back()->with('error', 'Could not parse any items from the receipt.');
        }

        // Delete existing items and replace with parsed ones
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

        $this->audit->log('receipt_parsed', $bill, [
            'item_count' => count($items),
        ]);

        return back()->with('success', 'Receipt parsed successfully.');
    }

    private function billPayload(Bill $bill): array
    {
        return [
            'id' => $bill->id,
            'title' => $bill->title,
            'description' => $bill->description,
            'total_cents' => $bill->total_cents,
            'total' => Money::format($bill->total_cents),
            'status' => $bill->status,
            'public_token' => $bill->public_token,
            'receipt_image_path' => $bill->receipt_image_path,
            'is_published' => $bill->is_published,
            'created_at' => $bill->created_at->toIso8601String(),
        ];
    }
}
