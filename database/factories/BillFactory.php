<?php

namespace Database\Factories;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'public_token' => Str::random(32),
            'title' => fake()->words(3, true),
            'organizer_name' => fake()->name(),
            'total_cents' => 10000,
            'status' => 'draft',
        ];
    }
}
