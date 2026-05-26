<?php

namespace Tests\Feature;

use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_redirects_to_bill_when_demo_token_is_set()
    {
        config(['splitlah.demo_bill_token' => 'valid-demo-token']);
        $bill = Bill::factory()->create(['public_token' => 'valid-demo-token']);

        $response = $this->get(route('demo'));

        $response->assertRedirect(route('bills.show', $bill));
    }

    /** @test */
    public function it_falls_back_to_default_token_when_config_is_empty()
    {
        config(['splitlah.demo_bill_token' => null]);
        $bill = Bill::factory()->create(['public_token' => 'demo-friday-nasi-kandar']);

        $response = $this->get(route('demo'));

        $response->assertRedirect(route('bills.show', $bill));
    }

    /** @test */
    public function it_returns_404_when_no_bill_matches_token()
    {
        config(['splitlah.demo_bill_token' => 'nonexistent']);

        $response = $this->get(route('demo'));

        $response->assertNotFound();
    }
}
