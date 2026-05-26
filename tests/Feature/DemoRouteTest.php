<?php

namespace Tests\Feature;

use Database\Seeders\DemoBillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_redirects_to_seeded_bill(): void
    {
        $this->seed(DemoBillSeeder::class);
        $token = config('splitlah.demo_bill_token', 'demo-friday-nasi-kandar');

        $this->get('/demo')
            ->assertRedirect("/bills/{$token}");
    }
}
