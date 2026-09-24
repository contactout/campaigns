<?php

namespace Database\Factories;

use App\Models\CampaignEmail;
use App\Models\LinkClick;
use App\Models\Recipient;
use App\Models\TrackedLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkClick>
 */
class LinkClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracked_link_id' => TrackedLink::factory(),
            'campaign_email_id' => CampaignEmail::factory(),
            'recipient_id' => Recipient::factory(),
            'clicked_at' => now(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
