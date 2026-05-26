<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Participant extends Model
{
    protected $fillable = [
        'bill_id',
        'name',
        'amount_cents',
        'status',
        'token',
        'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (Participant $participant) {
            if (empty($participant->token)) {
                $participant->token = Str::random(64);
            }
        });
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'manual_paid']);
    }
}