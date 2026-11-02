<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailboxReader;
use App\Data\InboundMessage;
use App\Data\MailboxBatch;
use App\Enums\MailerType;
use App\Exceptions\Mail\MailerHttpException;
use App\Models\MailerConnection;
use App\Services\OAuth\OAuthTokenManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
     * Messages are read oldest first, following `@odata.nextLink` and
     * skipping `$skip` ids, until `$limit` messages are in hand. Token or Graph failures are thrown rather
     * than returned as an empty mailbox, so the caller never moves its cursor
     * past messages that were not fetched.
     *
     * @param  array<int, string>  $skip  Internet message ids already processed.
     *
     * @throws MailerHttpException When a Graph request fails.
     * @throws RuntimeException When the access token cannot be obtained.
     */
    public function fetch(MailerConnection $connection, ?CarbonInterface $since, int $limit, ?CarbonInterface $before = null, array $skip = []): MailboxBatch
    {
        if ($connection->mailer_type !== MailerType::Outlook) {
            return new MailboxBatch([]);
        }

        $settings = $connection->smtp_setting ?? [];

        if (empty($settings['access_token']) && empty($settings['refresh_token'])) {
            return new MailboxBatch([]);
        }

        $accessToken = $this->tokens->accessToken($connection);

        $query = [
            '$top' => min($limit, 100),
            '$select' => 'internetMessageId,conversationId,subject,from,bodyPreview,body,receivedDateTime,internetMessageHeaders',
            '$orderby' => 'receivedDateTime asc',
        ];

        $filters = [];

        if ($since instanceof CarbonInterface) {
            $filters[] = 'receivedDateTime ge '.$since->toIso8601String();
        }

        if ($before instanceof CarbonInterface) {
            $filters[] = 'receivedDateTime lt '.$before->toIso8601String();
        }

        if ($filters !== []) {
            $query['$filter'] = implode(' and ', $filters);
        }

        $url = 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages';
        $skip = array_flip($skip);
        $inbound = [];

        do {
            $response = Http::withToken($accessToken)->get($url, $query);

            if (! $response->successful()) {
                throw MailerHttpException::fromResponse('Outlook mailbox read failed.', $response);
            }

            $payload = $response->json();
            $payload = is_array($payload) ? $payload : [];
            $values = $payload['value'] ?? [];

            foreach (is_array($values) ? $values : [] as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $inboundMessage = $this->toInboundMessage($message);

                if (! isset($skip[$inboundMessage->resumeId()])) {
                    $inbound[] = $inboundMessage;
                }
            }

            // The next link already carries the query, and passing any query
            // array (even an empty one) would replace its query string.
            $next = $payload['@odata.nextLink'] ?? null;
            $url = is_string($next) && $next !== '' ? $next : null;
            $query = null;
        } while ($url !== null && count($inbound) < $limit);

        return new MailboxBatch(
            array_slice($inbound, 0, $limit),
            $url !== null || count($inbound) > $limit,
        );
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
        $receivedAt = $message['receivedDateTime'] ?? null;
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
            receivedAt: is_string($receivedAt) && $receivedAt !== '' ? CarbonImmutable::parse($receivedAt) : null,
        );
    }
}
