<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Participant;
use App\Models\PaymentRequest;
use App\Services\Fiuu\FiuuGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiuuPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_marks_paid_with_valid_skey(): void
    {
        config([
            'services.fiuu.merchant_id' => 'SB_TEST',
            'services.fiuu.secret_key' => 'secret',
            'services.fiuu.verify_key' => 'verify',
        ]);

        $bill = Bill::create([
            'title' => 'Test',
            'organizer_name' => 'Org',
            'total_cents' => 1000,
            'status' => 'published',
        ]);
        $participant = $bill->participants()->create(['name' => 'Ali', 'amount_cents' => 1000]);
        $pr = PaymentRequest::create([
            'participant_id' => $participant->id,
            'bill_id' => $bill->id,
            'order_id' => 'SLHTEST001',
            'amount_cents' => 1000,
            'status' => 'pending',
        ]);

        $payload = [
            'tranID' => 'T1',
            'orderid' => 'SLHTEST001',
            'status' => '00',
            'domain' => 'SB_TEST',
            'amount' => '10.00',
            'currency' => 'MYR',
            'appcode' => '',
            'paydate' => '2026-05-21 10:00:00',
        ];
        $pre = md5('T1'.'SLHTEST001'.'00'.'SB_TEST'.'10.00'.'MYR');
        $payload['skey'] = md5('2026-05-21 10:00:00'.'SB_TEST'.$pre.''.'secret');

        $this->post('/payments/fiuu/notify', $payload)->assertOk();
        $this->assertEquals('paid', $pr->fresh()->status);
        $this->assertEquals('paid', $participant->fresh()->status);
    }

    public function test_invalid_skey_does_not_mark_paid(): void
    {
        config(['services.fiuu.secret_key' => 'secret', 'services.fiuu.merchant_id' => 'SB']);
        $bill = Bill::create(['title' => 'T', 'organizer_name' => 'O', 'total_cents' => 500, 'status' => 'published']);
        $participant = $bill->participants()->create(['name' => 'X', 'amount_cents' => 500]);
        PaymentRequest::create([
            'participant_id' => $participant->id,
            'bill_id' => $bill->id,
            'order_id' => 'ORDX',
            'amount_cents' => 500,
            'status' => 'pending',
        ]);

        $this->post('/payments/fiuu/notify', [
            'orderid' => 'ORDX',
            'status' => '00',
            'skey' => 'bad',
        ])->assertOk();

        $this->assertEquals('unpaid', $participant->fresh()->status);
    }
}
