<?php

namespace App\Support;

class Money
{
    public static function format(int $cents, string $currency = 'MYR'): string
    {
        $amount = number_format($cents / 100, 2, '.', '');

        return $currency === 'MYR' ? 'RM'.$amount : $currency.' '.$amount;
    }

    public static function fromDecimal(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
