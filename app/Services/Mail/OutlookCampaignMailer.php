<?php

namespace App\Services\Mail;

use App\Contracts\Mail\CampaignMailer;
use App\Data\SendResult;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Services\OAuth\OAuthTokenManager;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends campaign emails through Microsoft Graph.
 */
class OutlookCampaignMailer implements CampaignMailer
{
    /**
     * Create a new Outlook campaign mailer instance.
     */
    public function __construct(private readonly OAuthTokenManager $tokens) {}

    /**
     * Send an HTML email using the connection's Outlook OAuth credentials.
     *
     * Uses create → send → get so we can capture internetMessageId and
     * conversationId when Graph still exposes the message after send.
     *
     * @param  array<string, string>  $headers  Sent as Graph internetMessageHeaders.
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = []): SendResult
    {
        if ($connection->mailer_type !== MailerType::Outlook) {
            throw new RuntimeException('OutlookCampaignMailer requires an Outlook connection.');
        }

        $settings = $connection->smtp_setting ?? [];
        $accessToken = $this->tokens->accessToken($connection);

        $fromEmail = (string) ($settings['from_email'] ?? $settings['email'] ?? '');
        $fromName = (string) ($settings['from_name'] ?? $settings['name'] ?? '');

        $message = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $html,
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $to,
                    ],
                ],
            ],
        ];

        if ($headers !== []) {
            $message['internetMessageHeaders'] = collect($headers)
                ->map(fn (string $value, string $name): array => ['name' => $name, 'value' => $value])
                ->values()
                ->all();
        }

        if ($fromEmail !== '') {
            $message['from'] = [
                'emailAddress' => array_filter([
                    'address' => $fromEmail,
                    'name' => $fromName !== '' ? $fromName : null,
                ]),
            ];
        }

        $create = Http::withToken($accessToken)
            ->post('https://graph.microsoft.com/v1.0/me/messages', $message);

        if (! $create->successful()) {
            throw new RuntimeException('Outlook message create failed.');
        }

        $created = $create->json();
        $id = is_array($created) ? (string) ($created['id'] ?? '') : '';

        if ($id === '') {
            throw new RuntimeException('Outlook message create returned no id.');
        }

        $send = Http::withToken($accessToken)
            ->post('https://graph.microsoft.com/v1.0/me/messages/'.$id.'/send');

        if (! $send->successful()) {
            throw new RuntimeException('Outlook message send failed.');
        }

        $get = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me/messages/'.$id, [
                '$select' => 'internetMessageId,conversationId',
            ]);

        if (! $get->successful()) {
            return new SendResult;
        }

        $payload = $get->json();

        if (! is_array($payload)) {
            return new SendResult;
        }

        $messageId = isset($payload['internetMessageId']) ? (string) $payload['internetMessageId'] : null;
        $threadId = isset($payload['conversationId']) ? (string) $payload['conversationId'] : null;

        return new SendResult(
            messageId: $messageId !== '' ? $messageId : null,
            threadId: $threadId !== '' ? $threadId : null,
        );
    }
}
