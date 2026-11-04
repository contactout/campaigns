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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

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

test('resolves custom fields whose names contain spaces', function () {
    $contact = Contact::factory()->create();
    $field = ContactField::factory()->forTeam($contact->team)->create(['name' => 'Job Title']);

    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Engineer',
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => Campaign::factory()->create()->id,
        'contact_id' => $contact->id,
    ]);

    expect(app(PlaceholderRenderer::class)->render('Role: {{Job Title}} / {{ job title }}', $recipient))
        ->toBe('Role: Engineer / Engineer');
});

test('resolves a body placeholder whose field name the editor stored HTML-escaped', function () {
    $contact = Contact::factory()->create();
    $field = ContactField::factory()->forTeam($contact->team)->create(['name' => 'R&D']);

    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => 'Lab <1>',
    ]);

    $recipient = Recipient::factory()->create([
        'campaign_id' => Campaign::factory()->create()->id,
        'contact_id' => $contact->id,
    ]);
    $renderer = app(PlaceholderRenderer::class);

    expect($renderer->render('<p>{{R&amp;D}}</p>', $recipient))->toBe('<p>Lab &lt;1&gt;</p>')
        ->and($renderer->renderText('{{R&D}}', $recipient))->toBe('Lab <1>');
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

test('renders the plain-text connection signature inside a signature-only paragraph', function () {
    $team = Team::factory()->create();
    $signature = Signature::factory()->forTeam($team)->create(['body' => "Best regards\nAda Lovelace\nTom & Jerry <ada@example.com>"]);
    $connection = MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);

    $html = app(PlaceholderRenderer::class)->render(
        '<p>Hi {{name}}</p><p>{{ Signature }}</p>',
        rendererSignatureRecipient($team),
        $connection,
    );

    expect($html)->toBe("<p>Hi Grace Hopper</p><p>Best regards<br />\nAda Lovelace<br />\nTom &amp; Jerry &lt;ada@example.com&gt;</p>");
});

test('renders existing html signatures literally instead of interpreting markup or entities', function () {
    $team = Team::factory()->create();
    $signature = Signature::factory()->forTeam($team)->create(['body' => 'Line<br>Line &amp; <a href="https://example.com">Sales</a>']);
    $connection = MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);

    $html = app(PlaceholderRenderer::class)->render(
        '<p>{{signature}}</p>',
        rendererSignatureRecipient($team),
        $connection,
    );

    expect($html)->toBe('<p>Line&lt;br&gt;Line &amp;amp; &lt;a href=&quot;https://example.com&quot;&gt;Sales&lt;/a&gt;</p>');
});

test('renders an inline signature tag and leaves placeholders in the signature untouched', function () {
    $team = Team::factory()->create();
    $signature = Signature::factory()->forTeam($team)->create(['body' => "Alex {{name}}\r\nSales & Support"]);
    $connection = MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);

    $html = app(PlaceholderRenderer::class)->render(
        '<p>Thanks, {{signature}}</p>',
        rendererSignatureRecipient($team),
        $connection,
    );

    expect($html)->toBe("<p>Thanks, Alex {{name}}<br />\r\nSales &amp; Support</p>");
});

test('falls back to the team default signature when the connection has none', function () {
    $team = Team::factory()->create();
    Signature::factory()->forTeam($team)->default()->create(['body' => "The team\nSales & Support"]);
    Signature::factory()->default()->create(['body' => '<p>Another team</p>']);
    $connection = MailerConnection::factory()->forTeam($team)->create();

    $html = app(PlaceholderRenderer::class)->render('<p>{{signature}}</p>', rendererSignatureRecipient($team), $connection);

    expect($html)->toBe("<p>The team<br />\nSales &amp; Support</p>");
});

test('renders the signature tag empty without a connection or any signature', function () {
    $team = Team::factory()->create();
    $recipient = rendererSignatureRecipient($team);
    $connection = MailerConnection::factory()->forTeam($team)->create();

    $renderer = app(PlaceholderRenderer::class);

    expect($renderer->render('<p>Bye</p><p>{{signature}}</p>', $recipient))->toBe('<p>Bye</p><p></p>')
        ->and($renderer->render('<p>Bye</p><p>{{signature}}</p>', $recipient, $connection))->toBe('<p>Bye</p><p></p>');
});

/**
 * Build a recipient whose contact name and custom field hold HTML-significant text.
 */
function rendererMarkupRecipient(Team $team): Recipient
{
    $contact = Contact::factory()->forTeam($team)->create(['name' => 'Tom <Ops> & Co']);
    $field = ContactField::factory()->forTeam($team)->create(['name' => 'website']);

    ContactProperty::factory()->create([
        'contact_id' => $contact->id,
        'contact_field_id' => $field->id,
        'value' => '"><a href="https://evil.example">click</a>',
    ]);

    return Recipient::factory()->create([
        'campaign_id' => Campaign::factory()->forTeam($team)->create()->id,
        'contact_id' => $contact->id,
    ]);
}

test('escapes contact values rendered into the html body', function () {
    $team = Team::factory()->create();
    $signature = Signature::factory()->forTeam($team)->create(['body' => '<p>Alex &amp; <b>Sales</b></p>']);
    $connection = MailerConnection::factory()->forTeam($team)->create(['signature_id' => $signature->id]);
    $recipient = rendererMarkupRecipient($team);

    $renderer = app(PlaceholderRenderer::class);
    $template = '<p>Hi {{name}}, <a href="https://example.com/?ref={{website}}">link</a></p>';
    $escaped = '<p>Hi Tom &lt;Ops&gt; &amp; Co, <a href="https://example.com/?ref=&quot;&gt;&lt;a href=&quot;https://evil.example&quot;&gt;click&lt;/a&gt;">link</a></p>';

    expect($renderer->render($template, $recipient))->toBe($escaped)
        ->and($renderer->render($template.'<p>{{signature}}</p>', $recipient, $connection))
        ->toBe($escaped.'<p>&lt;p&gt;Alex &amp;amp; &lt;b&gt;Sales&lt;/b&gt;&lt;/p&gt;</p>');
});

test('substitutes contact values unescaped into plain text', function () {
    $recipient = rendererMarkupRecipient(Team::factory()->create());

    expect(app(PlaceholderRenderer::class)->renderText('Hi {{ name }} {{nope}}', $recipient))
        ->toBe('Hi Tom <Ops> & Co ');
});

test('reads the team contact fields once when rendering a subject and body', function () {
    $team = Team::factory()->create();
    $recipient = rendererMarkupRecipient($team);
    $connection = MailerConnection::factory()->forTeam($team)->create();

    $fieldQueries = 0;

    DB::listen(function (QueryExecuted $query) use (&$fieldQueries): void {
        if (preg_match('/from [`"]?contact_fields[`"]?/i', $query->sql)) {
            $fieldQueries++;
        }
    });

    $renderer = app(PlaceholderRenderer::class);

    expect($renderer->renderText('Hi {{name}} {{website}}', $recipient))
        ->toBe('Hi Tom <Ops> & Co "><a href="https://evil.example">click</a>')
        ->and($renderer->render('<p>{{website}}</p>', $recipient, $connection))
        ->toBe('<p>&quot;&gt;&lt;a href=&quot;https://evil.example&quot;&gt;click&lt;/a&gt;</p>')
        ->and($fieldQueries)->toBe(1);
});
