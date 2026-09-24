<?php

namespace Database\Factories;

use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipient>
 */
class RecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => 'UTC',
            'status' => RecipientStatus::Active,
            'source' => 'manual',
            'placeholders' => [],
            'sequence' => 0,
            'next_scheduled_at' => null,
            'last_responded_at' => null,
            'last_delivered_at' => null,
        ];
    }
}
