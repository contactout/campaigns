<?php

use App\Enums\ContactIdentityType;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactIdentity;
use App\Models\ContactProperty;
use App\Models\MailerConnection;
use App\Models\Recipient;
use App\Models\Signature;
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

/**
 * Build a recipient whose contact belongs to the given team.
 */
function rendererSignatureRecipient(Team $team): Recipient
{
    return Recipient::factory()->create([
        'campaign_id' => Campaign::factory()->forTeam($team)->create()->id,
        'contact_id' => Contact::factory()->forTeam($team)->create(['name' => 'Grace Hopper'])->id,
    ]);
}

test('renders the connection signature in place of a signature-only paragraph', function () {
    $team = Team::factory()->create();
    $signature = Signature::factory()->forTeam($team)->create(['body' => '<p>Alex</p><p>Sales</p>']);
    $connection = MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);

    $html = app(PlaceholderRenderer::class)->render(
        '<p>Hi {{name}}</p><p>{{ Signature }}</p>',
        rendererSignatureRecipient($team),
        $connection,
    );

    expect($html)->toBe('<p>Hi Grace Hopper</p><p>Alex</p><p>Sales</p>');
});

test('renders an inline signature tag and leaves placeholders in the signature untouched', function () {
    $team = Team::factory()->create();
    $signature = Signature::factory()->forTeam($team)->create(['body' => 'Alex {{name}}']);
    $connection = MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);

    $html = app(PlaceholderRenderer::class)->render(
        '<p>Thanks, {{signature}}</p>',
        rendererSignatureRecipient($team),
        $connection,
    );

    expect($html)->toBe('<p>Thanks, Alex {{name}}</p>');
});

test('falls back to the team default signature when the connection has none', function () {
    $team = Team::factory()->create();
    Signature::factory()->forTeam($team)->default()->create(['body' => '<p>The team</p>']);
    Signature::factory()->default()->create(['body' => '<p>Another team</p>']);
    $connection = MailerConnection::factory()->forTeam($team)->create();

    $html = app(PlaceholderRenderer::class)->render('<p>{{signature}}</p>', rendererSignatureRecipient($team), $connection);

    expect($html)->toBe('<p>The team</p>');
});

test('renders the signature tag empty without a connection or any signature', function () {
    $team = Team::factory()->create();
    $recipient = rendererSignatureRecipient($team);
    $connection = MailerConnection::factory()->forTeam($team)->create();

    $renderer = app(PlaceholderRenderer::class);

    expect($renderer->render('<p>Bye</p><p>{{signature}}</p>', $recipient))->toBe('<p>Bye</p><p></p>')
        ->and($renderer->render('<p>Bye</p><p>{{signature}}</p>', $recipient, $connection))->toBe('<p>Bye</p>');
});
