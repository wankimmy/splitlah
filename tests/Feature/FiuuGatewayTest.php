<?php

namespace Tests\Feature;

use App\Services\Fiuu\FiuuGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiuuGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.fiuu.merchant_id' => 'TEST_MERCHANT',
            'services.fiuu.verify_key' => 'test_verify_key',
            'services.fiuu.secret_key' => 'test_secret_key',
        ]);
    }

    /** @test */
    public function generate_vcode_returns_hmac_sha256_hash()
    {
        $gateway = new FiuuGateway();
        $vcode = $gateway->generateVcode('100.00', 'SLH20250320ABCDEFGHIJKL');

        $this->assertIsString($vcode);
        $this->assertEquals(64, strlen($vcode)); // SHA-256 hex is 64 chars
    }

    /** @test */
    public function generate_vcode_throws_exception_when_config_missing()
    {
        config(['services.fiuu.merchant_id' => null]);
        $gateway = new FiuuGateway();

        $this->expectException(\RuntimeException::class);
        $gateway->generateVcode('100.00', 'ORDER123');
    }

    /** @test */
    public function verify_skey_accepts_valid_signature()
    {
        $gateway = new FiuuGateway();
        $payload = [
            'tranID' => 'TXN123',
            'orderid' => 'SLH20250320ABCDEFGHIJKL',
            'status' => '1',
            'domain' => 'TEST_MERCHANT',
            'amount' => '100.00',
            'currency' => 'MYR',
        ];
        $payload['skey'] = hash_hmac('sha256', '100.00' . 'TEST_MERCHANT' . 'SLH20250320ABCDEFGHIJKL', 'test_verify_key');

        $this->assertTrue($gateway->verifySkey($payload));
    }

    /** @test */
    public function verify_skey_rejects_invalid_signature()
    {
        $gateway = new FiuuGateway();
        $payload = [
            'tranID' => 'TXN123',
            'orderid' => 'SLH20250320ABCDEFGHIJKL',
            'status' => '1',
            'domain' => 'TEST_MERCHANT',
            'amount' => '100.00',
            'currency' => 'MYR',
            'skey' => 'invalidhash',
        ];

        $this->assertFalse($gateway->verifySkey($payload));
    }

    /** @test */
    public function verify_skey_returns_false_when_config_missing()
    {
        config(['services.fiuu.verify_key' => null]);
        $gateway = new FiuuGateway();
        $payload = [
            'tranID' => 'TXN123',
            'orderid' => 'SLH20250320ABCDEFGHIJKL',
            'status' => '1',
            'domain' => 'TEST_MERCHANT',
            'amount' => '100.00',
            'currency' => 'MYR',
            'skey' => 'somehash',
        ];

        $this->assertFalse($gateway->verifySkey($payload));
    }

    /** @test */
    public function generate_order_id_has_minimum_entropy()
    {
        $gateway = new FiuuGateway();
        $orderId = $gateway->generateOrderId();

        $this->assertStringStartsWith('SLH', $orderId);
        // Date part is 8 digits, random part is 12 uppercase alphanumeric
        $this->assertMatchesRegularExpression('/^SLH\d{8}[A-Z0-9]{12}$/', $orderId);
    }
}
