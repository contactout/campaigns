<?php

use App\Models\CampaignEmail;
use App\Models\EmailOpen;
use App\Models\LinkClick;
use App\Models\TrackedLink;

test('an open records an email open and returns a transparent gif', function () {
    $email = CampaignEmail::factory()->create();

    $response = $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]));

    $response->assertOk()->assertHeader('Content-Type', 'image/gif');

    expect((string) $response->getContent())->toStartWith('GIF89a');

    $open = EmailOpen::query()->sole();

    expect($open->campaign_email_id)->toBe($email->id)
        ->and($open->recipient_id)->toBe($email->recipient_id)
        ->and($open->opened_at)->not->toBeNull()
        ->and($email->fresh()->opened_at)->not->toBeNull();
});

test('repeated opens are recorded each time', function () {
    $email = CampaignEmail::factory()->create();

    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]));
    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]));

    expect(EmailOpen::query()->count())->toBe(2)
        ->and($email->fresh()->opened_at)->not->toBeNull();
});

test('a click records a link click and redirects to the original url', function () {
    $email = CampaignEmail::factory()->create();

    $link = TrackedLink::factory()->create([
        'campaign_email_id' => $email->id,
        'url' => 'https://example.com/landing',
    ]);

    $this->withHeaders(['User-Agent' => 'MailClient/1.0'])
        ->get(route('tracking.click', ['hash' => $link->hash]))
        ->assertRedirect('https://example.com/landing');

    $click = LinkClick::query()->sole();

    expect($click->tracked_link_id)->toBe($link->id)
        ->and($click->campaign_email_id)->toBe($email->id)
        ->and($click->recipient_id)->toBe($email->recipient_id)
        ->and($click->user_agent)->toBe('MailClient/1.0');
});

test('an unknown tracking hash returns not found', function () {
    $this->get('/t/c/'.str_repeat('a', 32))->assertNotFound();
});

test('an email is given a unique random tracker on creation', function () {
    $first = CampaignEmail::factory()->create(['tracker' => '']);
    $second = CampaignEmail::factory()->create(['tracker' => '']);

    expect($first->tracker)->toHaveLength(32)
        ->and($second->tracker)->toHaveLength(32)
        ->and($first->tracker)->not->toBe($second->tracker);
});

test('the open pixel cannot be reached by numeric email id', function () {
    $email = CampaignEmail::factory()->create();

    $this->get('/t/o/'.$email->id)->assertNotFound();

    expect(EmailOpen::query()->count())->toBe(0)
        ->and($email->fresh()->opened_at)->toBeNull();
});
