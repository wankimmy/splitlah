<?php

namespace App\Services\Fiuu;

use App\Models\Participant;
use App\Support\Money;
use Illuminate\Http\Request;
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

    public function createPaymentRequest(Participant $participant): array
    {
        $orderId = $this->generateOrderId();
        $amount = Money::toDecimal($participant->amount_cents);

        $payload = [
            'order_id'    => $orderId,
            'amount'      => $amount,
            'bill_name'   => $participant->name,
            'bill_email'  => $participant->email,
            'bill_phone'  => $participant->phone,
            'return_url'  => route('fiuu.return', ['participantPayment' => $participant->token]),
            'callback_url'=> route('fiuu.callback'),
        ];

        $payload['signature'] = $this->sign($payload);

        return $payload;
    }

    public function sign(array $data): string
    {
        $secret = config('services.fiuu.secret');
        $sorted = $data;
        ksort($sorted);
        $string = '';
        foreach ($sorted as $key => $value) {
            $string .= $key . $value;
        }
        return hash('sha512', $string . $secret);
    }

    public function validateWebhook(Request $request): bool
    {
        $receivedSig = $request->header('x-signature');
        if (empty($receivedSig)) {
            Log::warning('Fiuu webhook missing x-signature header');
            return false;
        }

        $data = $request->all();
        unset($data['signature']);
        $secret = config('services.fiuu.secret');
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= $key . $value;
        }
        $expected = hash('sha512', $string . $secret);

        return hash_equals($expected, $receivedSig);
    }
}
