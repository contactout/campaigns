<?php

use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\TrackedLink;
use App\Services\Mail\CampaignBodyBuilder;
use Illuminate\Support\Facades\URL;

/**
 * Create an email whose campaign carries the given settings.
 *
 * @param  array<string, mixed>  $settings
 */
function mmosTrackedEmail(array $settings = []): CampaignEmail
{
    $campaign = Campaign::factory()->create(['settings' => $settings]);

    return CampaignEmail::factory()->create(['campaign_id' => $campaign->id]);
}

test('rewrites absolute links to tracked urls', function () {
    $email = CampaignEmail::factory()->create();

    $html = (new CampaignBodyBuilder)->build(
        $email,
        '<p>Hi there</p><a href="https://example.com/page?x=1">Visit us</a>',
    );

    $link = TrackedLink::query()->sole();

    expect($link->campaign_email_id)->toBe($email->id)
        ->and($link->url)->toBe('https://example.com/page?x=1')
        ->and($html)->toContain(route('tracking.click', ['hash' => $link->hash]))
        ->and($html)->not->toContain('href="https://example.com/page?x=1"');
});

test('reuses a tracked link when the same url is built again', function () {
    $email = CampaignEmail::factory()->create();
    $builder = new CampaignBodyBuilder;
    $body = '<a href="https://example.com/promo">Promo</a>';

    $first = $builder->build($email, $body);
    $second = $builder->build($email, $body);

    expect(TrackedLink::query()->count())->toBe(1)
        ->and($first)->toBe($second)
        ->and(substr_count($second, 't/c/'))->toBe(1);
});

test('leaves mailto and relative links untouched', function () {
    $email = CampaignEmail::factory()->create();

    $html = (new CampaignBodyBuilder)->build(
        $email,
        '<a href="mailto:ada@example.com">Mail</a><a href="/local-page">Local</a>',
    );

    expect($html)->toContain('href="mailto:ada@example.com"')
        ->and($html)->toContain('href="/local-page"')
        ->and(TrackedLink::query()->count())->toBe(0);
});

test('appends the tracking pixel when there is no body tag', function () {
    $email = CampaignEmail::factory()->create();

    $html = (new CampaignBodyBuilder)->build($email, '<p>Hi there</p>');

    expect($html)->toContain(
        '<img src="'.route('tracking.open', ['campaignEmail' => $email->tracker]).'" width="1" height="1" alt="" style="display:none" />',
    );
});

test('inserts the tracking pixel before the closing body tag', function () {
    $email = CampaignEmail::factory()->create();

    $html = (new CampaignBodyBuilder)->build($email, '<html><body><p>Hi there</p></body></html>');

    $pixel = '<img src="'.route('tracking.open', ['campaignEmail' => $email->tracker]).'" width="1" height="1" alt="" style="display:none" />';

    expect($html)->toContain($pixel.'</body>');
});

test('adds an unsubscribe link to the signed confirmation page', function () {
    $email = CampaignEmail::factory()->create();

    $html = (new CampaignBodyBuilder)->build($email, '<p>Hi there</p>');

    expect($html)->toContain(URL::signedRoute('unsubscribe.show', ['recipient' => $email->recipient_id]));
});

test('does not wrap the unsubscribe link in click tracking', function () {
    $email = CampaignEmail::factory()->create();

    $html = (new CampaignBodyBuilder)->build($email, '<p>Hi there</p>');

    expect(TrackedLink::query()->count())->toBe(0)
        ->and($html)->not->toContain('t/c/');
});

test('skips link rewriting when link tracking is off', function () {
    $email = mmosTrackedEmail(['link_tracking' => false]);

    $html = (new CampaignBodyBuilder)->build($email, '<a href="https://example.com/page">Visit us</a>');

    expect(TrackedLink::query()->count())->toBe(0)
        ->and($html)->toContain('href="https://example.com/page"');
});

test('skips the open pixel when open tracking is off', function () {
    $email = mmosTrackedEmail(['open_tracking' => false]);

    $html = (new CampaignBodyBuilder)->build($email, '<p>Hi there</p>');

    expect($html)->not->toContain(route('tracking.open', ['campaignEmail' => $email->tracker]));
});

test('still adds the unsubscribe link when both kinds of tracking are off', function () {
    $email = mmosTrackedEmail(['open_tracking' => false, 'link_tracking' => false]);

    $html = (new CampaignBodyBuilder)->build($email, '<a href="https://example.com/page">Visit us</a>');

    expect($html)->toContain(URL::signedRoute('unsubscribe.show', ['recipient' => $email->recipient_id]))
        ->and($html)->toContain('href="https://example.com/page"')
        ->and($html)->not->toContain('t/c/');
});
