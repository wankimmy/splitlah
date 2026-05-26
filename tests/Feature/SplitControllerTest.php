<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentage_share_is_persisted(): void
    {
        $bill = Bill::create([
            'title' => 'Lunch',
            'organizer_name' => 'Org',
            'total_cents' => 10000,
            'status' => 'draft',
        ]);
        $p1 = $bill->participants()->create(['name' => 'A', 'amount_cents' => 0]);
        $p2 = $bill->participants()->create(['name' => 'B', 'amount_cents' => 0]);

        $this->post("/bills/{$bill->public_token}/split", [
            'split_mode' => 'percentage',
            'percentages' => [
                $p1->id => 60,
                $p2->id => 40,
            ],
        ])->assertRedirect();

        $this->assertEquals(60, (float) $p1->fresh()->percentage_share);
        $this->assertEquals(40, (float) $p2->fresh()->percentage_share);
    }

    public function test_foreign_assignment_ids_are_rejected(): void
    {
        $bill = Bill::create([
            'title' => 'Lunch',
            'organizer_name' => 'Org',
            'total_cents' => 5000,
            'status' => 'draft',
        ]);
        $participant = $bill->participants()->create(['name' => 'A', 'amount_cents' => 0]);
        $item = BillItem::create([
            'bill_id' => $bill->id,
            'name' => 'Nasi',
            'total_price_cents' => 5000,
        ]);

        $otherBill = Bill::create(['title' => 'Other', 'organizer_name' => 'O', 'total_cents' => 1000, 'status' => 'draft']);
        $foreignItem = BillItem::create([
            'bill_id' => $otherBill->id,
            'name' => 'Foreign',
            'total_price_cents' => 1000,
        ]);

        $response = $this->from("/bills/{$bill->public_token}/split")
            ->post("/bills/{$bill->public_token}/split", [
                'split_mode' => 'itemized',
                'assignments' => [
                    $foreignItem->id => [$participant->id],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Invalid item assignment.');
    }
}
