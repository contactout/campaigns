<?php

use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Recipient;
use Illuminate\Database\QueryException;

test('recipient contact must be unique per campaign', function () {
    $campaign = Campaign::factory()->create();
    $contact = Contact::factory()->create();

    Recipient::factory()->for($campaign)->create(['contact_id' => $contact->id]);

    expect(fn () => Recipient::factory()->for($campaign)->create(['contact_id' => $contact->id]))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('recipients', 1);
});

test('the same contact can be added to different campaigns', function () {
    $firstCampaign = Campaign::factory()->create();
    $secondCampaign = Campaign::factory()->create();
    $contact = Contact::factory()->create();

    Recipient::factory()->for($firstCampaign)->create(['contact_id' => $contact->id]);
    Recipient::factory()->for($secondCampaign)->create(['contact_id' => $contact->id]);

    expect(Recipient::where('contact_id', $contact->id)->count())->toBe(2);
});

test('recipient belongs to a contact', function () {
    $contact = Contact::factory()->create();

    $recipient = Recipient::factory()->create(['contact_id' => $contact->id]);

    expect($recipient->contact->is($contact))->toBeTrue();
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
