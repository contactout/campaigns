<?php

use App\Models\CampaignEmail;
use App\Models\MailerConnection;
use App\Services\Mail\CampaignBodyBuilder;
use App\Services\Mail\CampaignMessageBuilder;
use Illuminate\Support\Facades\URL;

test('campaign plain text keeps rendered links and unsubscribe but omits the tracking pixel', function (bool $tracking) {
    $email = CampaignEmail::factory()->create();
    $email->campaign->update(['settings' => ['open_tracking' => $tracking, 'link_tracking' => $tracking]]);
    $html = (new CampaignBodyBuilder)->build($email, '<p>Hello Zoë &amp; Ada<br>Welcome!</p>'
        .'<p><a href="https://example.com/?a=1&amp;b=2">Our site</a></p>');

    $message = (new CampaignMessageBuilder)->build(
        MailerConnection::factory()->create(),
        'ada@example.com',
        'Hello',
        $html,
        toName: 'Zoë, Ada',
    );

    $url = $tracking ? route('tracking.click', ['hash' => $email->trackedLinks()->sole()->hash]) : 'https://example.com/?a=1&b=2';

    expect($message->getTextBody())->toContain("Hello Zoë & Ada\nWelcome!", 'Our site <'.$url.'>',
        'Unsubscribe <'.URL::signedRoute('unsubscribe.show', ['recipient' => $email->recipient_id]).'>')
        ->not->toContain('<p>', '<img', route('tracking.open', ['campaignEmail' => $email->tracker]))
        ->and($message->getHtmlBody())->toBe($html)
        ->and($message->getTo()[0]->getName())->toBe('Zoë, Ada')
        ->and($message->toString())->toContain('multipart/alternative;', 'Content-Type: text/plain;', 'Content-Type: text/html;');
})->with([true, false]);

test('plain text handles html fragments and excludes non-body content', function () {
    $message = (new CampaignMessageBuilder)->build(
        MailerConnection::factory()->create(),
        'ada@example.com',
        'Hello',
        '<html><head><title>Not body</title><style>.hidden { color: red }</style></head><body>'
        .'<div>First</div><div>Second</div><ul><li>One</li><li>Two</li></ul>'
        .'<a href="https://example.com/">https://example.com/</a><script>ignore()</script>'
        .'<img src="https://example.com/pixel" alt=""></body></html>',
    );

    expect($message->getTextBody())->toBe("First\n\nSecond\n\nOne\n\nTwo\nhttps://example.com/");
});

test('display names with quotes, brackets and backslashes survive unchanged', function (string $name) {
    $message = (new CampaignMessageBuilder)->build(MailerConnection::factory()->create(), 'ada@example.com', 'Hello', '<p>Hi</p>', toName: $name);

    expect($message->getTo()[0]->getName())->toBe($name)
        ->and($message->getTo()[0]->getAddress())->toBe('ada@example.com');

    $message->toString();
})->with(['Ada <CEO>', 'Ada "The Countess" L', 'Ada \\ L']);
