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
        'status', 'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Participant $participant) {
            $participant->token = Str::random(64);
        });
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'manual_paid']);
    }
}
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