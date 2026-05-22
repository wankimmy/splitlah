<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRequest extends Model
{
    protected $fillable = [
        'participant_id', 'bill_id', 'order_id', 'provider', 'amount_cents', 'currency',
        'status', 'fiuu_tran_id', 'fiuu_channel', 'fiuu_appcode', 'fiuu_paydate',
        'request_payload_json', 'response_payload_json', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload_json' => 'array',
            'response_payload_json' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class);
    }
}
