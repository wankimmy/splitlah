<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\RedirectResponse;

class DemoController extends Controller
{
    public function show(): RedirectResponse
    {
        $bill = Bill::where('title', 'Friday Nasi Kandar Lunch')->first();
        if (! $bill) {
            return redirect()->route('home')->with('error', 'Demo bill not found. Run php artisan db:seed.');
        }

        return redirect()->route('bills.show', $bill);
    }
}
