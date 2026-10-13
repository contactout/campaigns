<?php

use App\Enums\CampaignStatus;
use App\Enums\EmailStatus;
use App\Jobs\Campaigns\SendEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\MailerConnection;
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

test('dispatches at most one email per interval for a spaced connection', function () {
    Queue::fake();

    $spaced = MailerConnection::factory()->create(['min_seconds_between_sends' => 60]);
    $waiting = MailerConnection::factory()->create([
        'min_seconds_between_sends' => 60,
        'last_sent_at' => now()->subSeconds(30),
    ]);
    $unspaced = MailerConnection::factory()->create();

    $campaign = Campaign::factory()->active()->create(['mailer_connection_id' => $spaced->id]);
    $first = mmosDueEmail($campaign, EmailStatus::Scheduled, now()->subMinutes(2));
    $second = mmosDueEmail($campaign, EmailStatus::Scheduled, now()->subMinute());

    $waitingEmail = mmosDueEmail($campaign, EmailStatus::Scheduled, now()->subMinute());
    $waitingEmail->update(['mailer_connection_id' => $waiting->id]);

    $unspacedEmails = collect([
        mmosDueEmail($campaign, EmailStatus::Scheduled, now()->subMinute()),
        mmosDueEmail($campaign, EmailStatus::Scheduled, now()->subMinute()),
    ])->each(fn (CampaignEmail $email) => $email->update(['mailer_connection_id' => $unspaced->id]));

    $this->artisan('campaigns:dispatch-due')
        ->expectsOutputToContain('Dispatched 3')
        ->assertSuccessful();

    Queue::assertPushed(SendEmail::class, fn (SendEmail $job): bool => $job->email->is($first));
    Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->email->is($second));
    Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->email->is($waitingEmail));
    $unspacedEmails->each(fn (CampaignEmail $email) => Queue::assertPushed(
        SendEmail::class,
        fn (SendEmail $job): bool => $job->email->is($email),
    ));
});

test('reports gracefully when nothing is due', function () {
    Queue::fake();

    $this->artisan('campaigns:dispatch-due')
        ->expectsOutputToContain('No campaign emails are due.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
