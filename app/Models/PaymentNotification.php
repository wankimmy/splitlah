<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentNotification extends Model
{
    protected $fillable = [
        'payment_request_id', 'provider', 'order_id', 'tran_id', 'status',
        'is_valid_signature', 'payload_json', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'is_valid_signature' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }
}
