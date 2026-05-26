<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Bill;
use Illuminate\Http\RedirectResponse;

trait GuardsPublishedBill
{
    protected function rejectIfPublished(Bill $bill): ?RedirectResponse
    {
        if ($bill->isPublished()) {
            return redirect()->route('bills.show', $bill)
                ->with('error', 'This bill is published. Receipt and split can no longer be edited.');
        }

        return null;
    }
}
