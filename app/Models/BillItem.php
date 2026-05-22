<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillItem extends Model
{
    protected $fillable = [
        'bill_id', 'name', 'quantity', 'unit_price_cents', 'total_price_cents',
        'sort_order', 'source', 'is_fee',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'is_fee' => 'boolean',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ItemAssignment::class);
    }
}
