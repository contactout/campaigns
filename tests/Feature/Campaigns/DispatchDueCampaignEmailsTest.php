<?php

use App\Enums\CampaignStatus;
use App\Enums\EmailStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Queue;

/**
 * Create a campaign email with a known campaign/status/schedule.
 */
function mmosDueEmail(Campaign $campaign, EmailStatus $status, CarbonInterface $scheduledAt): CampaignEmail
{
    $step = CampaignStep::factory()->forCampaign($campaign)->create();
    $recipient = Recipient::factory()->create(['campaign_id' => $campaign->id]);

    return CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $step->id,
        'recipient_id' => $recipient->id,
        'status' => $status,
        'scheduled_at' => $scheduledAt,
    ]);
}

test('dispatches send jobs for due emails on active campaigns', function () {
    Queue::fake();

    $active = Campaign::factory()->active()->create();
    $due = mmosDueEmail($active, EmailStatus::Scheduled, now()->subMinute());

    $future = mmosDueEmail($active, EmailStatus::Scheduled, now()->addHour());

    $stopped = Campaign::factory()->create(['status' => CampaignStatus::Stopped]);
    $stoppedEmail = mmosDueEmail($stopped, EmailStatus::Scheduled, now()->subMinute());

    $this->artisan('campaigns:dispatch-due')
        ->expectsOutputToContain('Dispatched 1')
        ->assertSuccessful();

    Queue::assertPushed(SendEmail::class, 1);
    Queue::assertPushed(SendEmail::class, fn (SendEmail $job): bool => $job->email->is($due));
    Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->email->is($future));
    Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->email->is($stoppedEmail));
});

test('reports gracefully when nothing is due', function () {
    Queue::fake();

    $this->artisan('campaigns:dispatch-due')
        ->expectsOutputToContain('No campaign emails are due.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
