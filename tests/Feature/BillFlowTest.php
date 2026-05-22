<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Participant;
use Database\Seeders\DemoBillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_bill_and_participants(): void
    {
        $response = $this->post('/bills', [
            'title' => 'Mamak Night',
            'organizer_name' => 'Safwan',
            'participants' => [
                ['name' => 'Ali'],
                ['name' => 'Abu'],
            ],
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['title' => 'Mamak Night']);
        $this->assertEquals(2, Participant::count());
    }

    public function test_participant_payment_page_loads(): void
    {
        $this->seed(DemoBillSeeder::class);
        $participant = Participant::first();
        $this->get('/pay/'.$participant->token)->assertOk();
        $participant->refresh();
        $this->assertNotNull($participant->last_opened_at);
    }

    public function test_demo_bill_exists_after_seed(): void
    {
        $this->seed(DemoBillSeeder::class);
        $this->assertDatabaseHas('bills', ['title' => 'Friday Nasi Kandar Lunch']);
    }
}
