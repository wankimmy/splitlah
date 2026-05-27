<?php

namespace App\Http\Middleware;

use App\Services\Fiuu\FiuuGateway;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyFiuuSignature
{
    public function __construct(private FiuuGateway $gateway) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->gateway->validateWebhook($request)) {
            Log::warning('Fiuu webhook: invalid signature', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);
            return response('Invalid signature', 400);
        }

        return $next($request);
    }
}
