<?php

namespace App\Services\Payment;

use App\Models\Bill;
use App\Models\Participant;
use App\Support\Money;

class PaymentLinkService
{
    public function paymentUrl(Participant $participant): string
    {
        return url('/pay/'.$participant->token);
    }

    public function qrValue(Participant $participant): string
    {
        if (config('services.fiuu.enabled') && config('services.fiuu.duitnow_channel')) {
            return route('payments.fiuu.create', $participant->token);
        }

        return $this->paymentUrl($participant);
    }

    public function shareMessage(Participant $participant): string
    {
        $bill = $participant->bill;
        $amount = Money::format($participant->amount_cents);
        $url = $this->paymentUrl($participant);

        if ($bill->due_date) {
            return "Hi {$participant->name}, your share for {$bill->title} is {$amount}.\nPay here: {$url}\n\nPlease pay before {$bill->due_date->format('d M Y')}. Thanks!";
        }

        return "Hi {$participant->name}, your share for {$bill->title} is {$amount}.\nPay here: {$url}\n\nThanks!";
    }

    public function reminderMessage(Participant $participant): string
    {
        $bill = $participant->bill;
        $amount = Money::format($participant->amount_cents);
        $url = $this->paymentUrl($participant);

        return "Hi {$participant->name}, gentle reminder for {$bill->title}.\nAmount pending: {$amount}\nPay here: {$url}\n\nThank you!";
    }

    public function whatsAppUrl(Participant $participant, bool $reminder = false): string
    {
        $message = $reminder ? $this->reminderMessage($participant) : $this->shareMessage($participant);
        $phone = preg_replace('/\D/', '', (string) $participant->phone);
        $base = 'https://wa.me/'.($phone ?: '');
        $query = http_build_query(['text' => $message]);

        return $phone ? "{$base}?{$query}" : 'https://wa.me/?'.$query;
    }

    public function billSummary(Bill $bill): string
    {
        $bill->load('participants');
        $collected = $bill->collectedCents();
        $pending = max(0, $bill->total_cents - $collected);
        $lines = [
            $bill->title,
            'Total: '.Money::format($bill->total_cents),
            'Collected: '.Money::format($collected),
            'Pending: '.Money::format($pending),
            '',
            'Paid:',
        ];

        foreach ($bill->participants->whereIn('status', ['paid', 'manual_paid']) as $p) {
            $lines[] = '- '.$p->name.' '.Money::format($p->amount_cents);
        }

        $lines[] = '';
        $lines[] = 'Pending:';
        foreach ($bill->participants->whereNotIn('status', ['paid', 'manual_paid']) as $p) {
            $lines[] = '- '.$p->name.' '.Money::format($p->amount_cents);
        }

        return implode("\n", $lines);
    }
}
