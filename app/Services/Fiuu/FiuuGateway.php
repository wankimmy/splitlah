<?php

namespace App\Services\Fiuu;

use App\Models\Participant;
use App\Support\Money;
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
        return md5($amount.config('services.fiuu.merchant_id').$orderId.config('services.fiuu.verify_key'));
    }

    public function verifySkey(array $payload): bool
    {
        $tranID = (string) ($payload['tranID'] ?? '');
        $orderid = (string) ($payload['orderid'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $merchant = (string) ($payload['domain'] ?? config('services.fiuu.merchant_id'));
        $amount = (string) ($payload['amount'] ?? '');
        $currency = (string) ($payload['currency'] ?? '');
        $appcode = (string) ($payload['appcode'] ?? '');
        $paydate = (string) ($payload['paydate'] ?? '');
        $skey = (string) ($payload['skey'] ?? '');

        $preSkey = md5($tranID.$orderid.$status.$merchant.$amount.$currency);
        $expected = md5($paydate.$merchant.$preSkey.$appcode.config('services.fiuu.secret_key'));

        return hash_equals($expected, $skey);
    }

    public function mapStatus(string $status): string
    {
        return match ($status) {
            '00' => 'paid',
            '11' => 'failed',
            '22' => 'pending',
            default => 'failed',
        };
    }

    public function paymentActionUrl(): string
    {
        $base = rtrim((string) config('services.fiuu.pay_base_url'), '/');
        $merchantId = config('services.fiuu.merchant_id');

        return $base.'/'.$merchantId.'/';
    }

    public function buildHostedPayload(Participant $participant, string $orderId): array
    {
        $amount = Money::toDecimal($participant->amount_cents);
        $bill = $participant->bill;

        $payload = [
            'amount' => $amount,
            'orderid' => $orderId,
            'bill_name' => $participant->name,
            'bill_email' => $participant->email,
            'bill_mobile' => $participant->phone ?: '60123456789',
            'bill_desc' => 'Splitlah: '.$bill->title,
            'country' => config('services.fiuu.country', 'MY'),
            'cur' => config('services.fiuu.currency', 'MYR'),
            'vcode' => $this->generateVcode($amount, $orderId),
            'returnurl' => route('payments.fiuu.return'),
            'callbackurl' => route('payments.fiuu.notify'),
            'cancelurl' => route('payments.fiuu.cancel'),
        ];

        if ($method = config('services.fiuu.payment_method')) {
            $payload['payment_method'] = $method;
        }

        if ($channel = config('services.fiuu.duitnow_channel')) {
            $payload['channel'] = $channel;
        }

        return $payload;
    }
}
