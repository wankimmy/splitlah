<?php

namespace App\Services\Fiuu;

use App\Models\Participant;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FiuuGateway
{
    public function isEnabled(): bool
    {
        return (bool) config('services.fiuu.enabled');
    }

    public function generateOrderId(): string
    {
        return 'SLH'.now()->format('Ymd').Str::upper(Str::random(6));
    }

    public function generateVcode(string $amount, string $orderId): string
    {
        try {
            $merchantId = config('services.fiuu.merchant_id');
            $verifyKey = config('services.fiuu.verify_key');
            if (empty($merchantId) || empty($verifyKey)) {
                Log::error('FiuuGateway: Missing merchant_id or verify_key in config.');
                throw new \RuntimeException('Fiuu configuration incomplete.');
            }
            return hash('sha512', $amount . $merchantId . $orderId . $verifyKey);
        } catch (\Throwable $e) {
            Log::error('FiuuGateway::generateVcode failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                // amount and keys are intentionally omitted
            ]);
            throw $e;
        }
    }

    public function verifySkey(array $payload): bool
    {
        try {
            $tranID = (string) ($payload['tranID'] ?? '');
            $orderid = (string) ($payload['orderid'] ?? '');
            $status = (string) ($payload['status'] ?? '');
            $merchant = (string) ($payload['domain'] ?? config('services.fiuu.merchant_id'));
            $amount = (string) ($payload['amount'] ?? '');
            $currency = (string) ($payload['currency'] ?? '');
            $appcode = (string) ($payload['appcode'] ?? '');
            $paydate = (string) ($payload['paydate'] ?? '');
            $skey = (string) ($payload['skey'] ?? '');

            $secretKey = config('services.fiuu.secret_key');
            if (empty($secretKey)) {
                Log::error('FiuuGateway: Missing secret_key in config.');
                return false;
            }

            $preSkey = hash('sha512', $tranID . $orderid . $status . $merchant . $amount . $currency);
            $expected = hash('sha512', $paydate . $merchant . $preSkey . $appcode . $secretKey);

            return hash_equals($expected, $skey);
        } catch (\Throwable $e) {
            Log::error('FiuuGateway::verifySkey failed', [
                'error' => $e->getMessage(),
                'order_id' => $payload['orderid'] ?? 'unknown',
                // payload values are intentionally omitted to avoid leaking secrets
            ]);
            return false;
        }
    }

    public function mapStatus(string $status): string
    {
        // ... (unchanged)
    }
}