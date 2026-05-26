<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedBillLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_update_on_published_bill_is_blocked(): void
    {
        $bill = Bill::create([
            'title' => 'Published',
            'organizer_name' => 'Org',
            'total_cents' => 4000,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $bill->participants()->create(['name' => 'A', 'amount_cents' => 2000]);
        $bill->participants()->create(['name' => 'B', 'amount_cents' => 2000]);

        $response = $this->post("/bills/{$bill->public_token}/split", [
            'split_mode' => 'equal',
        ]);

        $response->assertRedirect(route('bills.show', $bill));
        $response->assertSessionHas('error');
    }

    public function test_receipt_upload_on_published_bill_is_blocked(): void
    {
        $bill = Bill::create([
            'title' => 'Published',
            'organizer_name' => 'Org',
            'total_cents' => 1000,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->post("/bills/{$bill->public_token}/receipt/upload", []);

        $response->assertRedirect(route('bills.show', $bill));
        $response->assertSessionHas('error');
    }
}
