<?php

use App\Models\CampaignEmail;
use App\Models\TrackedLink;
use App\Services\Mail\CampaignBodyBuilder;

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
        '<img src="'.route('tracking.open', ['campaignEmail' => $email->id]).'" width="1" height="1" alt="" style="display:none" />',
    );
});

test('inserts the tracking pixel before the closing body tag', function () {
    $email = CampaignEmail::factory()->create();

    $html = (new CampaignBodyBuilder)->build($email, '<html><body><p>Hi there</p></body></html>');

    $pixel = '<img src="'.route('tracking.open', ['campaignEmail' => $email->id]).'" width="1" height="1" alt="" style="display:none" />';

    expect($html)->toContain($pixel.'</body>');
});
