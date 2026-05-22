<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Participant extends Model
{
    protected $fillable = [
        'bill_id', 'token', 'name', 'email', 'phone',
        'amount_cents', 'subtotal_cents', 'tax_share_cents',
        'service_charge_share_cents', 'rounding_share_cents', 'adjustment_cents',
        'percentage_share', 'breakdown_json', 'status', 'paid_at',
        'last_shared_at', 'last_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'breakdown_json' => 'array',
            'paid_at' => 'datetime',
            'last_shared_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'percentage_share' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Participant $participant) {
            if (empty($participant->token)) {
                $participant->token = Str::random(32);
            }
            if (empty($participant->email)) {
                $participant->email = 'guest+'.$participant->token.'@splitlah.test';
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ItemAssignment::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'manual_paid'], true);
    }
}
