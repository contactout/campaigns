<?php

use App\Enums\EmailStatus;
use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\Recipient;
use App\Services\Mail\CampaignStepScheduler;
use Carbon\CarbonImmutable;

/**
 * Create an active campaign with a first step and a recipient of the given status.
 *
 * @return array{campaign: Campaign, step: CampaignStep, recipient: Recipient}
 */
function mmosSeededCampaign(RecipientStatus $status = RecipientStatus::Active, string $startedAt = '2026-01-01 00:00:00'): array
{
    $campaign = Campaign::factory()->active()->create([
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::parse($startedAt),
    ]);

    $step = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 1,
        'day' => 0,
        'time' => '09:00:00',
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => Contact::factory()->create()->id,
        'status' => $status,
    ]);

    return compact('campaign', 'step', 'recipient');
}

test('first step emails are scheduled for active and pending recipients', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00:00'));

    $campaign = Campaign::factory()->active()->create([
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::parse('2026-01-01 00:00:00'),
    ]);

    $step = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 1,
        'day' => 0,
        'time' => '09:00:00',
    ]);

    foreach ([RecipientStatus::Active, RecipientStatus::Active, RecipientStatus::Pending] as $status) {
        Recipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => Contact::factory()->create()->id,
            'status' => $status,
        ]);
    }

    // A completed recipient must not be seeded.
    Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => Contact::factory()->create()->id,
        'status' => RecipientStatus::Completed,
    ]);

    $count = app(CampaignStepScheduler::class)->scheduleFirstSteps($campaign);

    expect($count)->toBe(3)
        ->and(CampaignEmail::query()->where('campaign_id', $campaign->id)->count())->toBe(3);

    $email = CampaignEmail::query()->where('campaign_id', $campaign->id)->first();

    expect($email->status)->toBe(EmailStatus::Scheduled)
        ->and($email->campaign_step_id)->toBe($step->id)
        ->and($email->scheduled_at->toDateTimeString())->toBe('2026-01-01 09:00:00');
});

test('seeding skips emails that already exist for the recipient and step', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00:00'));

    ['campaign' => $campaign, 'step' => $step, 'recipient' => $recipient] = mmosSeededCampaign();

    CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $step->id,
        'recipient_id' => $recipient->id,
        'status' => EmailStatus::Scheduled,
    ]);

    $count = app(CampaignStepScheduler::class)->scheduleFirstSteps($campaign);

    expect($count)->toBe(0)
        ->and(CampaignEmail::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
});

test('seeding schedules about now when the computed time is in the past', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 12:00:00'));

    ['campaign' => $campaign] = mmosSeededCampaign(startedAt: '2026-01-01 00:00:00');

    app(CampaignStepScheduler::class)->scheduleFirstSteps($campaign);

    $email = CampaignEmail::query()->where('campaign_id', $campaign->id)->first();

    expect($email->scheduled_at->equalTo(CarbonImmutable::now()->addMinute()))->toBeTrue();
});

test('a recipient added after the campaign started is scheduled from their join date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-10 08:00:00'));

    ['campaign' => $campaign, 'recipient' => $recipient] = mmosSeededCampaign(startedAt: '2026-01-01 00:00:00');

    app(CampaignStepScheduler::class)->scheduleFirstSteps($campaign);

    $email = CampaignEmail::query()->where('recipient_id', $recipient->id)->sole();

    expect($email->scheduled_at->toDateTimeString())->toBe('2026-01-10 09:00:00');
});

test('later steps of a late-joining recipient stay offset from their join date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-10 08:00:00'));

    ['campaign' => $campaign, 'step' => $firstStep, 'recipient' => $recipient] = mmosSeededCampaign(startedAt: '2026-01-01 00:00:00');

    $secondStep = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 2,
        'day' => 3,
        'time' => '09:00:00',
    ]);

    $scheduler = app(CampaignStepScheduler::class);
    $scheduler->scheduleFirstSteps($campaign);

    $firstEmail = CampaignEmail::query()
        ->where('recipient_id', $recipient->id)
        ->where('campaign_step_id', $firstStep->id)
        ->sole();

    $scheduler->scheduleNextStep($firstEmail);

    $secondEmail = CampaignEmail::query()
        ->where('recipient_id', $recipient->id)
        ->where('campaign_step_id', $secondStep->id)
        ->sole();

    expect($secondEmail->scheduled_at->toDateTimeString())->toBe('2026-01-13 09:00:00');
});

test('a recipient added before the campaign started is scheduled from the campaign start', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-01 08:00:00'));

    ['campaign' => $campaign, 'recipient' => $recipient] = mmosSeededCampaign(startedAt: '2026-01-01 00:00:00');

    app(CampaignStepScheduler::class)->scheduleFirstSteps($campaign);

    $email = CampaignEmail::query()->where('recipient_id', $recipient->id)->sole();

    expect($email->scheduled_at->toDateTimeString())->toBe('2026-01-01 09:00:00');
});
