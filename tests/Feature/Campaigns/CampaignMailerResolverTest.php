<?php

use App\Data\SendResult;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Services\Mail\CampaignMailerResolver;
use App\Services\Mail\GmailCampaignMailer;
use App\Services\Mail\OutlookCampaignMailer;
use App\Services\Mail\SmtpCampaignMailer;

test('the resolver passes the recipient name to the mailer for every connection type', function (MailerType $type, string $mailer) {
    $connection = MailerConnection::factory()->create(['mailer_type' => $type]);

    $spy = Mockery::mock($mailer);
    $spy->shouldReceive('send')
        ->once()
        ->withArgs(fn ($c, $to, $subject, $html, $headers, $thread, $toName) => $to === 'ada@example.com' && $toName === 'Ada Lovelace')
        ->andReturn(new SendResult);

    $resolver = new CampaignMailerResolver(
        $mailer === SmtpCampaignMailer::class ? $spy : Mockery::mock(SmtpCampaignMailer::class),
        $mailer === GmailCampaignMailer::class ? $spy : Mockery::mock(GmailCampaignMailer::class),
        $mailer === OutlookCampaignMailer::class ? $spy : Mockery::mock(OutlookCampaignMailer::class),
    );

    $resolver->send($connection, 'ada@example.com', 'Hello', '<p>Hi</p>', toName: 'Ada Lovelace');
})->with([
    'smtp' => [MailerType::Smtp, SmtpCampaignMailer::class],
    'gmail' => [MailerType::Gmail, GmailCampaignMailer::class],
    'outlook' => [MailerType::Outlook, OutlookCampaignMailer::class],
]);
