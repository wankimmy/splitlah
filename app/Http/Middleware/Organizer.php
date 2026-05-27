<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Organizer
{
    public function handle(Request $request, Closure $next)
    {
        $sessionToken = session('organizer_token');
        if (!$sessionToken) {
            return redirect()->route('organizer.login');
        }

        // For routes with a bill parameter, verify ownership
        $bill = $request->route('bill');
        if ($bill && !hash_equals($bill->organizer_token, hash('sha256', $sessionToken))) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
