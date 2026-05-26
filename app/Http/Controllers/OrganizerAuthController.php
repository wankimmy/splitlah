<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerAuthController extends Controller
{
    public function showLoginForm(): Response
    {
        return Inertia::render('Organizer/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $hashedToken = hash('sha256', $request->token);
        $bill = Bill::where('organizer_token', $hashedToken)->first();

        if (!$bill || !hash_equals($bill->organizer_token, $hashedToken)) {
            return back()->withErrors(['token' => 'Invalid organizer token.']);
        }

        $request->session()->regenerate();
        session(['organizer_token' => $request->token]);

        return redirect()->route('bills.show', $bill);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->flush();
        return redirect()->route('home');
    }
}
