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

    // ... other methods unchanged
}