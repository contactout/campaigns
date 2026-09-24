<?php

namespace Database\Factories;

use App\Models\CampaignEmail;
use App\Models\EmailOpen;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailOpen>
 */
class EmailOpenFactory extends Factory
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
            'recipient_id' => Recipient::factory(),
            'opened_at' => now(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
