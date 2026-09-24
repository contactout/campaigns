<?php

use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\Recipient;
use Illuminate\Database\QueryException;

test('recipient email must be unique per campaign', function () {
    $campaign = Campaign::factory()->create();

    Recipient::factory()->for($campaign)->create(['email' => 'dup@example.com']);

    expect(fn () => Recipient::factory()->for($campaign)->create(['email' => 'dup@example.com']))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('recipients', 1);
});

test('the same email can be used in different campaigns', function () {
    $firstCampaign = Campaign::factory()->create();
    $secondCampaign = Campaign::factory()->create();

    Recipient::factory()->for($firstCampaign)->create(['email' => 'shared@example.com']);
    Recipient::factory()->for($secondCampaign)->create(['email' => 'shared@example.com']);

    expect(Recipient::where('email', 'shared@example.com')->count())->toBe(2);
});

test('recipient status is cast to an enum', function () {
    $recipient = Recipient::factory()->create(['status' => RecipientStatus::Unsubscribed]);

    expect($recipient->fresh()->status)->toBe(RecipientStatus::Unsubscribed);
});

test('recipient placeholders are cast to an array', function () {
    $recipient = Recipient::factory()->create([
        'placeholders' => ['first_name' => 'Ada', 'company' => 'Analytical Engines'],
    ]);

    expect($recipient->fresh()->placeholders)->toBe([
        'first_name' => 'Ada',
        'company' => 'Analytical Engines',
    ]);
});

test('forCampaign scope only returns recipients for the given campaign', function () {
    $campaign = Campaign::factory()->create();
    $otherCampaign = Campaign::factory()->create();

    Recipient::factory()->for($campaign)->create();
    Recipient::factory()->for($otherCampaign)->create();

    expect(Recipient::forCampaign($campaign->id)->count())->toBe(1);
});
