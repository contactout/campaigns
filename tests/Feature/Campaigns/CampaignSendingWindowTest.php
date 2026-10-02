<?php

use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\Recipient;
use App\Services\Mail\CampaignStepScheduler;
use Carbon\CarbonImmutable;

/**
 * Build an active campaign whose first step sends on day 0 at the given local time.
 *
 * @param  array<string, mixed>  $settings
 * @return array{campaign: Campaign, step: CampaignStep, recipient: Recipient}
 */
function mmosWindowCampaign(
    array $settings,
    string $startedAt = '2026-01-05 00:00:00',
    ?string $recipientTimezone = null,
    string $stepTime = '09:00:00',
): array {
    $campaign = Campaign::factory()->active()->create([
        'timezone' => 'UTC',
        'started_at' => CarbonImmutable::parse($startedAt, 'UTC'),
        'settings' => $settings,
    ]);

    $step = CampaignStep::factory()->forCampaign($campaign)->create([
        'sequence' => 1,
        'day' => 0,
        'time' => $stepTime,
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => Contact::factory()->create()->id,
        'timezone' => $recipientTimezone,
    ]);

    return compact('campaign', 'step', 'recipient');
}

/**
 * Schedule the campaign's first step and return the resulting UTC send time.
 */
function mmosScheduledAt(array $fixture): string
{
    app(CampaignStepScheduler::class)->scheduleFirstSteps($fixture['campaign']);

    return CampaignEmail::query()
        ->where('recipient_id', $fixture['recipient']->id)
        ->sole()
        ->scheduled_at
        ->toDateTimeString();
}

test('an unrestricted campaign keeps the step time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-04 00:00:00'));

    $fixture = mmosWindowCampaign(['sending_days' => [1, 2, 3, 4, 5, 6, 7], 'sending_hour_from' => 0, 'sending_hour_to' => 0]);

    expect(mmosScheduledAt($fixture))->toBe('2026-01-05 09:00:00');
});

test('a weekday window moves a weekend send to the next allowed day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-02 08:00:00'));

    // 2026-01-03 is a Saturday; the next allowed weekday is Monday 2026-01-05.
    $fixture = mmosWindowCampaign(['sending_days' => [1, 2, 3, 4, 5]], '2026-01-03 00:00:00');

    expect(mmosScheduledAt($fixture))->toBe('2026-01-05 09:00:00');
});

test('an hour window moves an early send to the opening hour', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-04 00:00:00'));

    $fixture = mmosWindowCampaign(
        ['sending_days' => [1, 2, 3, 4, 5, 6, 7], 'sending_hour_from' => 9, 'sending_hour_to' => 17],
        stepTime: '06:00:00',
    );

    expect(mmosScheduledAt($fixture))->toBe('2026-01-05 09:00:00');
});

test('an hour window moves a send after the window to the next day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-04 00:00:00'));

    $fixture = mmosWindowCampaign(
        ['sending_days' => [1, 2, 3, 4, 5, 6, 7], 'sending_hour_from' => 9, 'sending_hour_to' => 17],
        stepTime: '18:00:00',
    );

    expect(mmosScheduledAt($fixture))->toBe('2026-01-06 09:00:00');
});

test('a 23 to 0 window only allows the last hour of the day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-04 00:00:00'));

    $fixture = mmosWindowCampaign(
        ['sending_days' => [1, 2, 3, 4, 5, 6, 7], 'sending_hour_from' => 23, 'sending_hour_to' => 0],
    );

    expect(mmosScheduledAt($fixture))->toBe('2026-01-05 23:00:00');
});

test('the campaign timezone decides the local hour when the recipient has none', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-04 00:00:00'));

    $fixture = mmosWindowCampaign(['sending_days' => [1, 2, 3, 4, 5, 6, 7]]);

    expect(mmosScheduledAt($fixture))->toBe('2026-01-05 09:00:00');
});

test("a recipient's own timezone overrides the campaign timezone", function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-04 00:00:00'));

    // 09:00 in Auckland (UTC+13 in January) is 20:00 the previous day in UTC.
    $fixture = mmosWindowCampaign(
        ['sending_days' => [1, 2, 3, 4, 5, 6, 7]],
        recipientTimezone: 'Pacific/Auckland',
    );

    expect(mmosScheduledAt($fixture))->toBe('2026-01-04 20:00:00');
});

test('the sending window is evaluated in the recipient timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-04 00:00:00'));

    // 09:00 in New York on the campaign's start day is a Sunday, so the weekday
    // window pushes it to Monday 09:00 in New York, which is 14:00 UTC.
    $fixture = mmosWindowCampaign(
        ['sending_days' => [1, 2, 3, 4, 5]],
        recipientTimezone: 'America/New_York',
    );

    expect(mmosScheduledAt($fixture))->toBe('2026-01-05 14:00:00');
});
