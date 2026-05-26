<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\Participant;
use App\Services\Split\SplitCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private SplitCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SplitCalculatorService;
    }

    public function test_equal_split_distributes_remainder(): void
    {
        $bill = Bill::factory()->make(['total_cents' => 10000]);
        $bill->id = 1;
        $p1 = new Participant(['name' => 'A']);
        $p2 = new Participant(['name' => 'B']);
        $p3 = new Participant(['name' => 'C']);
        $p1->id = 1;
        $p2->id = 2;
        $p3->id = 3;
        $results = $this->service->calculateEqual($bill, collect([$p1, $p2, $p3]));
        $this->assertEquals(10000, array_sum(array_column($results, 'amount_cents')));
    }

    public function test_manual_split_validation(): void
    {
        $this->assertTrue($this->service->validateTotal([5000, 5000], 10000));
        $this->assertFalse($this->service->validateTotal([5000, 4000], 10000));
    }

    public function test_percentage_split_totals_bill(): void
    {
        $bill = Bill::factory()->make(['total_cents' => 10000]);
        $results = $this->service->calculatePercentage($bill, [1 => 60, 2 => 40]);
        $this->assertEquals(10000, array_sum(array_column($results, 'amount_cents')));
    }
}