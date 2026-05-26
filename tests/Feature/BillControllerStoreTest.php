<?php

namespace Tests\Feature;

use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillControllerStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_bill_and_redirects()
    {
        $response = $this->post(route('bills.store'), [
            'title' => 'Test Bill',
            'organizer_name' => 'John',
            'organizer_email' => 'john@example.com',
            'description' => 'A test bill',
            'due_date' => now()->addDays(7)->toDateString(),
            'participants' => [
                ['name' => 'Alice', 'phone' => '0123456789', 'email' => 'alice@example.com'],
                ['name' => 'Bob', 'phone' => '0123456790', 'email' => 'bob@example.com'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['title' => 'Test Bill']);
        $this->assertDatabaseCount('participants', 2);
    }

    public function test_store_validation_requires_title()
    {
        $response = $this->post(route('bills.store'), [
            'organizer_name' => 'John',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ]);
        $response->assertSessionHasErrors('title');
    }

    public function test_store_requires_at_least_two_participants()
    {
        $response = $this->post(route('bills.store'), [
            'title' => 'Test',
            'organizer_name' => 'John',
            'participants' => [
                ['name' => 'Alice'],
            ],
        ]);
        $response->assertSessionHasErrors('participants');
    }
}