<?php

namespace Tests\Unit;

use App\Services\Fiuu\FiuuGateway;
use Tests\TestCase;

class FiuuGatewayTest extends TestCase
{
    public function test_vcode_generation(): void
    {
        config([
            'services.fiuu.merchant_id' => 'TESTMERCHANT',
            'services.fiuu.verify_key' => 'verify123',
        ]);
        $gateway = new FiuuGateway;
        $vcode = $gateway->generateVcode('12.90', 'SLH202605210001');
        $this->assertEquals(32, strlen($vcode));
    }

    public function test_skey_verification(): void
    {
        config([
            'services.fiuu.merchant_id' => 'domain1',
            'services.fiuu.secret_key' => 'secret1',
        ]);
        $gateway = new FiuuGateway;
        $payload = [
            'tranID' => '123',
            'orderid' => 'ORD1',
            'status' => '00',
            'domain' => 'domain1',
            'amount' => '12.90',
            'currency' => 'MYR',
            'appcode' => '',
            'paydate' => '2026-05-21 12:00:00',
        ];
        $pre = md5('123'.'ORD1'.'00'.'domain1'.'12.90'.'MYR');
        $payload['skey'] = md5('2026-05-21 12:00:00'.'domain1'.$pre.''.'secret1');
        $this->assertTrue($gateway->verifySkey($payload));
        $payload['skey'] = 'invalid';
        $this->assertFalse($gateway->verifySkey($payload));
    }

    public function test_status_mapping(): void
    {
        $gateway = new FiuuGateway;
        $this->assertEquals('paid', $gateway->mapStatus('00'));
        $this->assertEquals('failed', $gateway->mapStatus('11'));
        $this->assertEquals('pending', $gateway->mapStatus('22'));
    }
}
