<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Services\OAuth\OAuthTokenManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads inbound mail from Outlook via Microsoft Graph.
 */
class OutlookMailboxReader implements MailboxReader
{
    /**
     * Create a new Outlook mailbox reader instance.
     */
    public function __construct(private readonly OAuthTokenManager $tokens) {}

    /**
     * Fetch inbox messages received after the given time, or the latest messages.
     *
     * @return array<int, InboundMessage>
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since): array
    {
        if ($connection->mailer_type !== MailerType::Outlook) {
            return [];
        }

        $settings = $connection->smtp_setting ?? [];

        if (empty($settings['access_token']) && empty($settings['refresh_token'])) {
            return [];
        }

        try {
            $accessToken = $this->tokens->accessToken($connection);
        } catch (Throwable) {
            return [];
        }

        $query = [
            '$top' => 50,
            '$select' => 'internetMessageId,conversationId,subject,from,bodyPreview,body,receivedDateTime,internetMessageHeaders',
            '$orderby' => 'receivedDateTime desc',
        ];

        if ($since instanceof CarbonInterface) {
            $query['$filter'] = 'receivedDateTime ge '.$since->toIso8601String();
        }

        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages', $query);

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();
        $values = is_array($payload) ? ($payload['value'] ?? []) : [];

        if (! is_array($values)) {
            return [];
        }

        $inbound = [];

        foreach ($values as $message) {
            if (! is_array($message)) {
                continue;
            }

            $inbound[] = $this->toInboundMessage($message);
        }

        return $inbound;
    }

    /**
     * Map a Graph message onto the normalized inbound message shape.
     *
     * @param  array<string, mixed>  $message
     */
    private function toInboundMessage(array $message): InboundMessage
    {
        $headers = [];

        foreach ($message['internetMessageHeaders'] ?? [] as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = strtolower((string) ($header['name'] ?? ''));
            $value = (string) ($header['value'] ?? '');

            if ($name !== '') {
                $headers[$name] = $value;
            }
        }

        $references = [];

        foreach (preg_split('/\s+/', trim($headers['references'] ?? '')) ?: [] as $reference) {
            if ($reference !== '') {
                $references[] = $reference;
            }
        }

        $inReplyTo = trim($headers['in-reply-to'] ?? '');
        $from = $message['from']['emailAddress']['address'] ?? null;
        $body = $message['body']['content'] ?? null;
        $text = is_string($body) && $body !== ''
            ? $body
            : (isset($message['bodyPreview']) ? (string) $message['bodyPreview'] : null);

        return new InboundMessage(
            messageId: (string) ($message['internetMessageId'] ?? $message['id'] ?? ''),
            inReplyTo: $inReplyTo !== '' ? $inReplyTo : null,
            references: $references,
            fromEmail: is_string($from) ? strtolower($from) : null,
            subject: (string) ($message['subject'] ?? ''),
            text: $text,
        );
    }
}
