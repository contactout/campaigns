<?php

namespace Database\Factories;

use App\Models\CampaignEmail;
use App\Models\TrackedLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrackedLink>
 */
class TrackedLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_email_id' => CampaignEmail::factory(),
            'url' => fake()->url(),
            'hash' => Str::random(32),
        ];
    }
}
