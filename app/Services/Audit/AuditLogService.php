<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Participant;
use App\Models\PaymentRequest;

class AuditLogService
{
    public function log(
        string $action,
        ?Bill $bill = null,
        ?Participant $participant = null,
        ?PaymentRequest $paymentRequest = null,
        ?string $actorType = null,
        ?string $actorName = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'bill_id' => $bill?->id ?? $participant?->bill_id ?? $paymentRequest?->bill_id,
            'participant_id' => $participant?->id ?? $paymentRequest?->participant_id,
            'payment_request_id' => $paymentRequest?->id,
            'action' => $action,
            'actor_type' => $actorType,
            'actor_name' => $actorName,
            'metadata' => $metadata ?: null,
        ]);
    }
}
