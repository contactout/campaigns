<?php

use App\Enums\EmailStatus;
use App\Models\CampaignEmail;
use App\Models\EmailOpen;
use App\Models\LinkClick;
use App\Models\TrackedLink;
use App\Support\TrackingUserAgent;
use Carbon\CarbonImmutable;

test('an open records an email open and returns a transparent gif', function () {
    $email = CampaignEmail::factory()->sent()->create();

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
    $email = CampaignEmail::factory()->sent()->create();

    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]));
    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]));

    expect(EmailOpen::query()->count())->toBe(2)
        ->and($email->fresh()->opened_at)->not->toBeNull();
});

test('a proxy fetch is not recorded but still returns the gif', function () {
    $email = CampaignEmail::factory()->sent()->create();

    $response = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) GoogleImageProxy',
    ])->get(route('tracking.open', ['campaignEmail' => $email->tracker]));

    $response->assertOk()->assertHeader('Content-Type', 'image/gif');

    expect(EmailOpen::query()->count())->toBe(0)
        ->and($email->fresh()->opened_at)->toBeNull();
});

test('a bare mozilla user agent is treated as a proxy fetch', function () {
    $email = CampaignEmail::factory()->sent()->create();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0'])
        ->get(route('tracking.open', ['campaignEmail' => $email->tracker]))
        ->assertOk();

    expect(EmailOpen::query()->count())->toBe(0);
});

test('an open is ignored for an email that was never sent', function () {
    $email = CampaignEmail::factory()->create();

    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    expect(EmailOpen::query()->count())->toBe(0)
        ->and($email->fresh()->opened_at)->toBeNull();
});

test('an open is ignored for a bounced email', function () {
    $email = CampaignEmail::factory()->sent()->create(['status' => EmailStatus::Bounced]);

    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]))->assertOk();

    expect(EmailOpen::query()->count())->toBe(0)
        ->and($email->fresh()->opened_at)->toBeNull();
});

test('a long user agent is cut to the length the column accepts', function () {
    $email = CampaignEmail::factory()->sent()->create();

    $this->withHeaders(['User-Agent' => str_repeat('a', 600)])
        ->get(route('tracking.open', ['campaignEmail' => $email->tracker]))
        ->assertOk();

    expect(EmailOpen::query()->sole()->user_agent)
        ->toHaveLength(TrackingUserAgent::MAX_LENGTH);
});

test('the first open timestamp is kept when the email is opened again', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 09:00:00'));

    $email = CampaignEmail::factory()->sent()->create();
    $link = TrackedLink::factory()->create(['campaign_email_id' => $email->id]);

    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]));
    $firstOpenedAt = $email->fresh()->opened_at;

    $this->travelTo(CarbonImmutable::parse('2026-03-01 11:00:00'));

    $this->get(route('tracking.open', ['campaignEmail' => $email->tracker]));
    $this->get(route('tracking.click', ['hash' => $link->hash]));

    expect(EmailOpen::query()->count())->toBe(2)
        ->and($email->fresh()->opened_at->equalTo($firstOpenedAt))->toBeTrue();
});

test('a click records a link click and redirects to the original url', function () {
    $email = CampaignEmail::factory()->sent()->create();

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

test('a click infers an open when the pixel was blocked', function () {
    $email = CampaignEmail::factory()->sent()->create();

    $link = TrackedLink::factory()->create(['campaign_email_id' => $email->id]);

    $this->get(route('tracking.click', ['hash' => $link->hash]))->assertRedirect();

    expect(EmailOpen::query()->count())->toBe(1)
        ->and($email->fresh()->opened_at)->not->toBeNull();
});

test('repeated clicks infer only one open', function () {
    $email = CampaignEmail::factory()->sent()->create();

    $link = TrackedLink::factory()->create(['campaign_email_id' => $email->id]);

    $this->get(route('tracking.click', ['hash' => $link->hash]))->assertRedirect();
    $this->get(route('tracking.click', ['hash' => $link->hash]))->assertRedirect();

    expect(LinkClick::query()->count())->toBe(2)
        ->and(EmailOpen::query()->count())->toBe(1);
});

test('a click records a trimmed user agent', function () {
    $email = CampaignEmail::factory()->sent()->create();

    $link = TrackedLink::factory()->create(['campaign_email_id' => $email->id]);

    $this->withHeaders(['User-Agent' => str_repeat('b', 600)])
        ->get(route('tracking.click', ['hash' => $link->hash]))
        ->assertRedirect();

    expect(LinkClick::query()->sole()->user_agent)
        ->toHaveLength(TrackingUserAgent::MAX_LENGTH);
});

test('a click on an unsent email still records the click', function () {
    $email = CampaignEmail::factory()->create();

    $link = TrackedLink::factory()->create(['campaign_email_id' => $email->id]);

    $this->get(route('tracking.click', ['hash' => $link->hash]))->assertRedirect();

    expect(LinkClick::query()->count())->toBe(1)
        ->and(EmailOpen::query()->count())->toBe(0);
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
