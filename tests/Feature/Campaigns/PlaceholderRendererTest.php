<?php

use App\Enums\ContactIdentityType;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactIdentity;
use App\Models\ContactProperty;
use App\Models\Recipient;
use App\Models\Team;
use App\Services\Mail\PlaceholderRenderer;

test('renders built-in and custom placeholders case-insensitively', function () {
    $team = Team::factory()->create();

    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Grace Hopper']);

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Email,
        'normalized_value' => 'grace@example.com',
    ]);

    ContactIdentity::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'identity_type' => ContactIdentityType::Phone,
        'normalized_value' => '+15551234567',
    ]);

    $field = ContactField::factory()->forTeam($team)->create(['name' => 'Company']);

    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'COBOL Inc',
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => Campaign::factory()->create()->id,
        'contact_id' => $contact->id,
    ]);

    $html = app(PlaceholderRenderer::class)->render(
        'Hi {{name}} <{{email}}> {{ phone }} {{COMPANY}} {{unknown}}',
        $recipient,
    );

    expect($html)->toBe('Hi Grace Hopper <grace@example.com> +15551234567 COBOL Inc ');
});

test('resolves unknown placeholders to an empty string', function () {
    $contact = Contact::factory()->create();
    $recipient = Recipient::factory()->create([
        'campaign_id' => Campaign::factory()->create()->id,
        'contact_id' => $contact->id,
    ]);

    $html = app(PlaceholderRenderer::class)->render('Before {{nope}} after', $recipient);

    expect($html)->toBe('Before  after');
});
