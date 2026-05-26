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
        return 'SLH'.now()->format('Ymd').Str::upper(Str::random(12));
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
            $domain = (string) ($payload['domain'] ?? config('services.fiuu.merchant_id'));
            $amount = (string) ($payload['amount'] ?? '');
            $currency = (string) ($payload['currency'] ?? '');
            $appcode = (string) ($payload['appcode'] ?? '');
            $paydate = (string) ($payload['paydate'] ?? '');
            $skey = (string) ($payload['skey'] ?? '');

            $secretKey = config('services.fiuu.secret_key');
            if (empty($secretKey)) {
                Log::error('FiuuGateway::verifySkey: Missing secret_key in config.');
                return false;
            }

            $preSkey = hash('sha512', $tranID . $orderid . $status . $domain . $amount . $currency);
            $expected = hash('sha512', $paydate . $domain . $preSkey . $appcode . $secretKey);

            return hash_equals($expected, $skey);
        } catch (\Throwable $e) {
            Log::error('FiuuGateway::verifySkey failed', [
                'error' => $e->getMessage(),
                'orderid' => $payload['orderid'] ?? 'unknown',
            ]);
            return false;
        }
    }

    /**
     * Verify webhook signature using HMAC-SHA256 with the merchant key.
     * Fiuu sends a signature in the X-Signature header or as a skey parameter.
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        // Prefer X-Signature header if available; fallback to skey verification (legacy)
        $signature = request()->header('X-Signature');
        if (!$signature) {
            // Fallback to skey verification (legacy)
            return $this->verifySkey($payload);
        }

        $merchantKey = config('services.fiuu.merchant_key');
        if (empty($merchantKey)) {
            Log::error('FiuuGateway::verifyWebhookSignature missing merchant_key');
            return false;
        }

        // Build the string to sign: concatenate key parameters sorted alphabetically
        $data = $this->buildSignatureString($payload);
        $computed = hash_hmac('sha256', $data, $merchantKey);

        return hash_equals($computed, $signature);
    }

    protected function buildSignatureString(array $payload): string
    {
        // Remove signature field itself
        unset($payload['skey'], $payload['signature']);
        ksort($payload);
        $parts = [];
        foreach ($payload as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode('&', $parts);
    }

    public function buildHostedPayload(Participant $participant, string $orderId): array
    {
        $amount = Money::toDecimal($participant->amount_cents);
        $vcode = $this->generateVcode($amount, $orderId);

        return [
            'merchant_id' => config('services.fiuu.merchant_id'),
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => 'MYR',
            'vcode' => $vcode,
            'bill_name' => $participant->bill->title,
            'bill_email' => config('services.fiuu.merchant_email'),
            'bill_mobile' => '',
            'bill_desc' => 'Payment for ' . $participant->name,
            'return_url' => route('fiuu.return'),
            'callback_url' => route('fiuu.callback'),
        ];
    }

    public function getHostedPaymentUrl(): string
    {
        return config('services.fiuu.hosted_url', 'https://sandbox.fiuu.com/payment');
    }
}