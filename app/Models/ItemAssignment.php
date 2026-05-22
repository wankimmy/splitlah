<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAssignment extends Model
{
    protected $fillable = ['bill_item_id', 'participant_id', 'share_cents'];

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
