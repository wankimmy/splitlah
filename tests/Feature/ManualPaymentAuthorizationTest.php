<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_token_alone_cannot_mark_paid(): void
    {
        $bill = Bill::create([
            'title' => 'Dinner',
            'organizer_name' => 'Org',
            'total_cents' => 2000,
            'status' => 'published',
        ]);
        $participant = $bill->participants()->create(['name' => 'Ali', 'amount_cents' => 2000]);

        $this->post("/participants/{$participant->token}/manual-paid", [
            'method' => 'cash',
        ])->assertNotFound();
    }

    public function test_bill_scoped_route_marks_paid(): void
    {
        $bill = Bill::create([
            'title' => 'Dinner',
            'organizer_name' => 'Org',
            'total_cents' => 2000,
            'status' => 'published',
        ]);
        $participant = $bill->participants()->create(['name' => 'Ali', 'amount_cents' => 2000]);

        $this->post("/bills/{$bill->public_token}/participants/{$participant->token}/manual-paid", [
            'method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals('manual_paid', $participant->fresh()->status);
    }

    public function test_wrong_bill_token_returns_not_found(): void
    {
        $billA = Bill::create(['title' => 'A', 'organizer_name' => 'O', 'total_cents' => 1000, 'status' => 'published']);
        $billB = Bill::create(['title' => 'B', 'organizer_name' => 'O', 'total_cents' => 1000, 'status' => 'published']);
        $participant = $billA->participants()->create(['name' => 'Ali', 'amount_cents' => 1000]);

        $this->post("/bills/{$billB->public_token}/participants/{$participant->token}/manual-paid", [
            'method' => 'cash',
        ])->assertNotFound();
    }
}
