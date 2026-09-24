<?php

use App\Enums\EmailStatus;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignStep;
use App\Models\Recipient;

test('campaign email status is cast to an enum', function () {
    $email = CampaignEmail::factory()->create(['status' => EmailStatus::Scheduled]);

    expect($email->fresh()->status)->toBe(EmailStatus::Scheduled);
});

test('scheduled scope only returns scheduled emails', function () {
    CampaignEmail::factory()->create(['status' => EmailStatus::Pending]);
    CampaignEmail::factory()->create(['status' => EmailStatus::Scheduled]);

    expect(CampaignEmail::scheduled()->count())->toBe(1);
});

test('pending scope only returns pending emails', function () {
    CampaignEmail::factory()->create(['status' => EmailStatus::Pending]);
    CampaignEmail::factory()->create(['status' => EmailStatus::Scheduled]);

    expect(CampaignEmail::pending()->count())->toBe(1);
});

test('campaign email relationships resolve to the same campaign', function () {
    $campaign = Campaign::factory()->create();
    $step = CampaignStep::factory()->for($campaign)->create();
    $recipient = Recipient::factory()->for($campaign)->create();

    $email = CampaignEmail::factory()->create([
        'campaign_id' => $campaign->id,
        'campaign_step_id' => $step->id,
        'recipient_id' => $recipient->id,
    ]);

    expect($email->campaign->is($campaign))->toBeTrue()
        ->and($email->step->is($step))->toBeTrue()
        ->and($email->recipient->is($recipient))->toBeTrue();
});
