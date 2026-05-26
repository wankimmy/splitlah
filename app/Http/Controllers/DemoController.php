<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\RedirectResponse;

class DemoController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $token = config('splitlah.demo_bill_token', 'demo-friday-nasi-kandar');
        $bill = Bill::where('public_token', $token)->firstOrFail();

        return redirect()->route('bills.show', $bill);
    }
}
