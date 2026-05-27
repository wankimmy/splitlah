<?php

namespace Tests\Feature;

use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_inertia_response()
    {
        $response = $this->get(route('organizer.login'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Organizer/Login'));
    }

    public function test_valid_token_login_sets_session_and_redirects()
    {
        $token = 'test-token-64-chars-long-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $bill = Bill::factory()->create([
            'organizer_token' => hash('sha256', $token),
        ]);

        $response = $this->post(route('organizer.login.store'), ['token' => $token]);

        $response->assertRedirect(route('bills.show', $bill));
        $this->assertEquals($token, session('organizer_token'));
    }

    public function test_invalid_token_login_fails_with_error()
    {
        $response = $this->post(route('organizer.login.store'), ['token' => 'invalid-token-64-chars-long-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx']);

        $response->assertSessionHasErrors('token');
        $this->assertNull(session('organizer_token'));
    }

    public function test_logout_clears_session_and_redirects()
    {
        $token = 'test-token-64-chars-long-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $bill = Bill::factory()->create([
            'organizer_token' => hash('sha256', $token),
        ]);

        // Login first
        $this->post(route('organizer.login.store'), ['token' => $token]);

        $response = $this->post(route('organizer.logout'));

        $response->assertRedirect(route('home'));
        $this->assertNull(session('organizer_token'));
    }

    public function test_unauthenticated_access_to_bill_routes_redirects_to_login()
    {
        $bill = Bill::factory()->create();

        $response = $this->get(route('bills.show', $bill));
        $response->assertRedirect(route('organizer.login'));
    }
}
