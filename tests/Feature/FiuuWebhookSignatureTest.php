<?php

namespace Tests\Feature;

use App\Models\PaymentRequest;
use App\Services\Fiuu\FiuuGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FiuuWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_missing_signature()
    {
        Config::set('services.fiuu.enabled', true);
        Config::set('services.fiuu.secret', 'test-secret');

        $response = $this->postJson('/fiuu/callback', [
            'order_id' => 'SLH20250101ABC123',
            'amount' => '10.00',
        ]);

        $response->assertStatus(400);
        $response->assertSee('Invalid signature');
    }

    public function test_webhook_rejects_invalid_signature()
    {
        Config::set('services.fiuu.enabled', true);
        Config::set('services.fiuu.secret', 'test-secret');

        $payload = [
            'order_id' => 'SLH20250101ABC123',
            'amount' => '10.00',
            'signature' => 'invalid-hash',
        ];

        $response = $this->postJson('/fiuu/callback', $payload);

        $response->assertStatus(400);
        $response->assertSee('Invalid signature');
    }

    public function test_webhook_accepts_valid_signature()
    {
        Config::set('services.fiuu.enabled', true);
        Config::set('services.fiuu.secret', 'test-secret');

        $gateway = app(FiuuGateway::class);
        $payload = [
            'order_id' => 'SLH20250101ABC123',
            'amount' => '10.00',
        ];
        $payload['signature'] = $gateway->sign($payload);

        // Create a payment request to avoid 404
        PaymentRequest::factory()->create(['order_id' => 'SLH20250101ABC123']);

        $response = $this->postJson('/fiuu/callback', $payload);

        $response->assertStatus(200);
    }
}
