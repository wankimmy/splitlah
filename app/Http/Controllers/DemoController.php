<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class DemoController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $token = config('splitlah.demo_bill_token');
        if (empty($token)) {
            Log::warning('DemoController: splitlah.demo_bill_token not set, using default demo token.');
            $token = 'demo-friday-nasi-kandar';
        }
        $bill = Bill::where('public_token', $token)->firstOrFail();

        return redirect()->route('bills.show', $bill);
    }
}