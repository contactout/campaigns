<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignStep>
 */
class CampaignStepFactory extends Factory
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
            'sequence' => 1,
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'day' => 0,
            'time' => '09:00:00',
            'is_threaded' => false,
            'setting' => null,
        ];
    }

    /**
     * Indicate that the step belongs to the given campaign.
     */
    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => $campaign->id,
        ]);
    }
}
