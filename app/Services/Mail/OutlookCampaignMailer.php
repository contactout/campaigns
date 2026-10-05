<?php

namespace App\Services\Mail;

use App\Contracts\Mail\CampaignMailer;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Services\OAuth\OAuthTokenManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends campaign emails through Microsoft Graph.
 *
 * Uses create → send → get so we can capture internetMessageId and
 * conversationId when Graph still exposes the message after send. Follow-ups
 * are created as replies on the previous message so Graph files them in the
 * same conversation; headers alone do not thread reliably.
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
     * @param  array<string, string>  $headers  Sent as Graph internetMessageHeaders.
     *
     * @throws RuntimeException When Graph rejects the send.
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
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

        $replyToId = $thread?->replyToId;

        $id = $replyToId !== null && $replyToId !== ''
            ? $this->createReply($accessToken, $replyToId, $message)
            : $this->createMessage($accessToken, $message);

        $this->sendMessage($accessToken, $id);

        // The Graph id is known even when the follow-up read fails, so a later
        // step can still reply to this message.
        $result = new SendResult(replyToId: $id);

        $get = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me/messages/'.$id, [
                '$select' => 'internetMessageId,conversationId',
            ]);

        if (! $get->successful()) {
            return $result;
        }

        $payload = $get->json();

        if (! is_array($payload)) {
            return $result;
        }

        $messageId = isset($payload['internetMessageId']) ? (string) $payload['internetMessageId'] : null;
        $threadId = isset($payload['conversationId']) ? (string) $payload['conversationId'] : null;

        return new SendResult(
            messageId: $messageId !== '' ? $messageId : null,
            threadId: $threadId !== '' ? $threadId : null,
            replyToId: $id,
        );
    }

    /**
     * Create a message, returning its Graph id.
     *
     * @param  array<string, mixed>  $message
     */
    private function createMessage(string $accessToken, array $message): string
    {
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

        return $id;
    }

    /**
     * Create a reply on the previous message and load the step content into it.
     *
     * Graph rejects `internetMessageHeaders` when updating a draft, so the reply
     * body, recipients and sender are patched in separately.
     *
     * @param  array<string, mixed>  $message
     */
    private function createReply(string $accessToken, string $replyToId, array $message): string
    {
        $create = Http::withToken($accessToken)
            ->post('https://graph.microsoft.com/v1.0/me/messages/'.$replyToId.'/createReply');

        if (! $create->successful()) {
            throw new RuntimeException('Outlook reply create failed.');
        }

        $created = $create->json();
        $id = is_array($created) ? (string) ($created['id'] ?? '') : '';

        if ($id === '') {
            throw new RuntimeException('Outlook reply create returned no id.');
        }

        $patch = Http::withToken($accessToken)
            ->patch(
                'https://graph.microsoft.com/v1.0/me/messages/'.$id,
                Arr::except($message, ['internetMessageHeaders']),
            );

        if (! $patch->successful()) {
            throw new RuntimeException('Outlook reply update failed.');
        }

        return $id;
    }

    /**
     * Send the created message.
     */
    private function sendMessage(string $accessToken, string $id): void
    {
        $send = Http::withToken($accessToken)
            ->post('https://graph.microsoft.com/v1.0/me/messages/'.$id.'/send');

        if (! $send->successful()) {
            throw new RuntimeException('Outlook message send failed.');
        }
    }
}
