<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_name', 'organizer_email', 'title', 'description',
        'due_date', 'currency', 'merchant_name', 'receipt_date',
        'subtotal_cents', 'tax_cents', 'service_charge_cents', 'rounding_cents', 'total_cents',
        'split_mode', 'tax_distribution', 'rounding_mode', 'status',
        'receipt_image_path', 'ocr_raw_text', 'ocr_parsed_json', 'ocr_confidence', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'receipt_date' => 'date',
            'ocr_parsed_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Bill $bill) {
            if (empty($bill->public_token)) {
                $bill->public_token = Str::random(32);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest();
    }

    public function collectedCents(): int
    {
        return (int) $this->participants()
            ->whereIn('status', ['paid', 'manual_paid'])
            ->sum('amount_cents');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
