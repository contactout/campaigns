<?php

namespace Database\Factories;

use App\Enums\EmailStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignEmail>
 */
class CampaignEmailFactory extends Factory
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
            'campaign_step_id' => CampaignStep::factory(),
            'recipient_id' => Recipient::factory(),
            'mailer_connection_id' => null,
            'thread_id' => '',
            'message_id' => '',
            'reply_to_id' => '',
            'tracker' => Str::random(32),
            'status' => EmailStatus::Pending,
            'data' => null,
            'reply_count' => 0,
            'scheduled_at' => null,
            'dispatched_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
            'replied_at' => null,
        ];
    }
}
