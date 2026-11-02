<?php

use App\Actions\Campaigns\StartCampaign;
use App\Actions\Campaigns\StopCampaign;
use App\Enums\CampaignStatus;
use App\Jobs\Campaigns\SeedCampaignEmails;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;
use App\Services\Mail\CampaignStepScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

test('starting a campaign marks it active and dispatches the seeding job', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    CampaignStep::factory()->forCampaign($campaign)->create();

    app(StartCampaign::class)->handle($campaign);

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Active)
        ->and($campaign->started_at)->not->toBeNull();

    Queue::assertPushed(SeedCampaignEmails::class, fn (SeedCampaignEmails $job): bool => $job->campaign->is($campaign));
});

test('running the seeding job creates the first step email for each recipient', function () {
    $campaign = Campaign::factory()->active()->create([
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::now(),
    ]);

    $step = CampaignStep::factory()->forCampaign($campaign)->create(['sequence' => 1]);

    Recipient::factory()->count(2)->create(['campaign_id' => $campaign->id]);

    $count = app(CampaignStepScheduler::class)->scheduleFirstSteps($campaign);

    expect($count)->toBe(2)
        ->and(CampaignEmail::query()->where('campaign_id', $campaign->id)->count())->toBe(2)
        ->and(CampaignEmail::query()->where('campaign_step_id', $step->id)->count())->toBe(2);
});

test('restarting a stopped campaign keeps the sequence anchored to the first start', function () {
    Queue::fake();

    $this->travelTo(CarbonImmutable::parse('2026-01-05 08:00:00', 'UTC'));

    $campaign = Campaign::factory()->create(['timezone' => 'UTC']);
    $step = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 1,
        'day' => 14,
        'time' => '09:00:00',
    ]);

    app(StartCampaign::class)->handle($campaign);

    $this->travel(5)->days();
    app(StopCampaign::class)->handle($campaign);

    $this->travel(1)->day();
    app(StartCampaign::class)->handle($campaign);

    $campaign->refresh();

    expect($campaign->started_at->toDateTimeString())->toBe('2026-01-05 08:00:00')
        ->and(app(CampaignStepScheduler::class)->scheduledAtFor($campaign, $step)->toDateTimeString())
        ->toBe('2026-01-19 09:00:00');
});
