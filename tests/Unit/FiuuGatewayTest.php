<?php

namespace Tests\Unit;

use App\Services\Fiuu\FiuuGateway;
use Tests\TestCase;

class FiuuGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.fiuu.merchant_id' => 'TEST_MERCHANT',
            'services.fiuu.verify_key' => 'TEST_VERIFY_KEY',
            'services.fiuu.secret_key' => 'TEST_SECRET_KEY',
        ]);
    }

    public function test_generate_vcode_uses_sha512()
    {
        $gateway = new FiuuGateway();
        $vcode = $gateway->generateVcode('100.00', 'SLH20250101ABCDEF');
        $this->assertEquals(128, strlen($vcode)); // SHA512 hex is 128 chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $vcode);
    }

    public function test_verify_skey_with_valid_payload()
    {
        $gateway = new FiuuGateway();
        $payload = [
            'tranID' => '123',
            'orderid' => 'SLH20250101ABCDEF',
            'status' => '1',
            'domain' => 'TEST_MERCHANT',
            'amount' => '100.00',
            'currency' => 'MYR',
            'appcode' => 'APP',
            'paydate' => '2025-01-01',
        ];
        $preSkey = hash('sha512', '123SLH20250101ABCDEF1TEST_MERCHANT100.00MYR');
        $expected = hash('sha512', '2025-01-01TEST_MERCHANT'.$preSkey.'APPTEST_SECRET_KEY');
        $payload['skey'] = $expected;

        $this->assertTrue($gateway->verifySkey($payload));
    }

    public function test_verify_skey_rejects_invalid_signature()
    {
        $gateway = new FiuuGateway();
        $payload = [
            'tranID' => '123',
            'orderid' => 'SLH20250101ABCDEF',
            'status' => '1',
            'domain' => 'TEST_MERCHANT',
            'amount' => '100.00',
            'currency' => 'MYR',
            'appcode' => 'APP',
            'paydate' => '2025-01-01',
            'skey' => 'invalid',
        ];
        $this->assertFalse($gateway->verifySkey($payload));
    }

    public function test_generate_vcode_throws_when_config_missing()
    {
        config(['services.fiuu.merchant_id' => null]);
        $gateway = new FiuuGateway();
        $this->expectException(\RuntimeException::class);
        $gateway->generateVcode('100.00', 'SLH20250101ABCDEF');
    }
}