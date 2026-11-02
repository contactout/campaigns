<?php

namespace App\Services\Mail;

use App\Contracts\Mail\GmailApi;
use Carbon\CarbonImmutable;
use Closure;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\MessagePartBody;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Throwable;

/**
 * Gmail API adapter backed by google/apiclient.
 */
class GoogleGmailApi implements GmailApi
{
    /**
     * Create a new adapter, optionally over a custom HTTP client.
     */
    public function __construct(
        private readonly ?ClientInterface $http = null,
    ) {}

    /**
     * Send a raw RFC822 message and return the provider ids.
     *
     * @return array{id: string, threadId: string}
     */
    public function sendRaw(string $accessToken, string $raw, ?string $threadId = null): array
    {
        try {
            $message = new Message;
            $message->setRaw($raw);

            if ($threadId !== null && $threadId !== '') {
                $message->setThreadId($threadId);
            }

            $sent = $this->gmail($accessToken)->users_messages->send('me', $message);
        } catch (Throwable $exception) {
            throw new RuntimeException('Gmail send failed: '.$exception->getMessage(), 0, $exception);
        }

        return [
            'id' => (string) $sent->getId(),
            'threadId' => (string) $sent->getThreadId(),
        ];
    }

    /**
     * Fetch the authenticated user's Gmail profile email address.
     */
    public function getProfileEmail(string $accessToken): string
    {
        try {
            $profile = $this->gmail($accessToken)->users->getProfile('me');
        } catch (Throwable $exception) {
            throw new RuntimeException('Gmail profile request failed: '.$exception->getMessage(), 0, $exception);
        }

        $email = (string) $profile->getEmailAddress();

        if ($email === '') {
            throw new RuntimeException('Gmail profile did not include an email address.');
        }

        return $email;
    }

    /**
     * List one page of message ids matching the given query.
     *
     * @return array{ids: array<int, string>, nextPageToken: string|null}
     */
    public function listMessageIds(string $accessToken, string $query, ?string $pageToken = null, int $maxResults = 100): array
    {
        $params = [
            'q' => $query,
            'maxResults' => $maxResults,
        ];

        if ($pageToken !== null && $pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }

        try {
            $list = $this->gmail($accessToken)->users_messages->listUsersMessages('me', $params);
        } catch (Throwable $exception) {
            throw new RuntimeException('Gmail message list failed: '.$exception->getMessage(), 0, $exception);
        }

        $ids = [];

        foreach ($list->getMessages() ?? [] as $message) {
            $id = (string) $message->getId();

            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $nextPageToken = (string) $list->getNextPageToken();

        return [
            'ids' => $ids,
            'nextPageToken' => $nextPageToken !== '' ? $nextPageToken : null,
        ];
    }

    /**
     * Fetch headers, body text and received time for a single message.
     *
     * @return array{messageId: string, inReplyTo: string|null, references: array<int, string>, fromEmail: string|null, subject: string, text: string|null, receivedAt: CarbonImmutable|null}|null
     */
    public function getMessage(string $accessToken, string $messageId): ?array
    {
        $gmail = $this->gmail($accessToken);

        try {
            $message = $gmail->users_messages->get('me', $messageId, [
                'format' => 'full',
            ]);
        } catch (GoogleServiceException $exception) {
            if ($exception->getCode() === 404) {
                return null;
            }

            throw new RuntimeException('Gmail message fetch failed: '.$exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('Gmail message fetch failed: '.$exception->getMessage(), 0, $exception);
        }

        $headers = [];

        foreach ($message->getPayload()?->getHeaders() ?? [] as $header) {
            $headers[strtolower((string) $header->getName())] = (string) $header->getValue();
        }

        $references = [];

        foreach (preg_split('/\s+/', trim($headers['references'] ?? '')) ?: [] as $reference) {
            if ($reference !== '') {
                $references[] = $reference;
            }
        }

        $inReplyTo = trim($headers['in-reply-to'] ?? '');
        $internalDate = (string) $message->getInternalDate();

        return [
            'messageId' => $headers['message-id'] ?? $messageId,
            'inReplyTo' => $inReplyTo !== '' ? $inReplyTo : null,
            'references' => $references,
            'fromEmail' => $this->extractEmail($headers['from'] ?? null),
            'subject' => $headers['subject'] ?? '',
            'text' => $this->bodyText(
                $message->getPayload(),
                fn (string $attachmentId): ?MessagePartBody => $this->attachment($gmail, $messageId, $attachmentId),
            ) ?? $message->getSnippet(),
            'receivedAt' => ctype_digit($internalDate)
                ? CarbonImmutable::createFromTimestampMs((int) $internalDate)
                : null,
        ];
    }

    /**
     * Fetch a part body Gmail stored as an attachment, or null if it is gone.
     */
    private function attachment(Gmail $gmail, string $messageId, string $attachmentId): ?MessagePartBody
    {
        try {
            return $gmail->users_messages_attachments->get('me', $messageId, $attachmentId);
        } catch (GoogleServiceException $exception) {
            if ($exception->getCode() === 404) {
                return null;
            }

            throw new RuntimeException('Gmail attachment fetch failed: '.$exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('Gmail attachment fetch failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Build an authenticated Gmail service client.
     */
    private function gmail(string $accessToken): Gmail
    {
        $client = new GoogleClient;
        $client->setAccessToken(['access_token' => $accessToken]);

        if ($this->http instanceof ClientInterface) {
            $client->setHttpClient($this->http);
        }

        return new Gmail($client);
    }

    /**
     * Extract an email address from a From header value.
     */
    private function extractEmail(?string $from): ?string
    {
        if ($from === null || $from === '') {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $from, $matches) === 1) {
            return strtolower($matches[1]);
        }

        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return strtolower($from);
        }

        return null;
    }

    /**
     * Get the readable text of a message payload.
     *
     * A multipart message, such as every `multipart/report` bounce, has no
     * top-level body, so its parts are walked in order. Plain text wins over
     * HTML alternatives, delivery status fields are kept (fetched when Gmail
     * stored them as an attachment), and named attachments are skipped. An
     * embedded copy of the original message is written after a
     * `Content-Type` line with its headers, so its Message-ID can be matched
     * while the bounce report itself still ends where the copy starts.
     *
     * @param  Closure(string): ?MessagePartBody  $attachment  Fetches a body Gmail stored as an attachment.
     */
    private function bodyText(?MessagePart $payload, Closure $attachment): ?string
    {
        $text = $payload instanceof MessagePart ? trim($this->partText($payload, $attachment)) : '';

        return $text !== '' ? $text : null;
    }

    /**
     * Collect the readable text of a MIME part and its children.
     *
     * @param  Closure(string): ?MessagePartBody  $attachment
     */
    private function partText(MessagePart $part, Closure $attachment): string
    {
        $mimeType = strtolower((string) $part->getMimeType());
        $children = $part->getParts();

        if (str_starts_with($mimeType, 'multipart/')) {
            if ($mimeType === 'multipart/alternative') {
                $plain = array_filter(
                    $children,
                    fn (MessagePart $child): bool => strtolower((string) $child->getMimeType()) === 'text/plain',
                );

                $children = $plain !== [] ? $plain : $children;
            }

            return $this->joinText(array_map(fn (MessagePart $child): string => $this->partText($child, $attachment), $children));
        }

        if ($mimeType === 'message/rfc822' || $mimeType === 'text/rfc822-headers') {
            $body = $mimeType === 'text/rfc822-headers'
                ? $this->readableText($part->getBody(), (string) $part->getFilename(), $attachment)
                : $this->extractText($part->getBody());

            $texts = ['Content-Type: '.$mimeType, $body ?? ''];

            foreach ($children as $child) {
                foreach ($child->getHeaders() as $header) {
                    $texts[] = $header->getName().': '.$header->getValue();
                }

                $texts[] = $this->partText($child, $attachment);
            }

            return $this->joinText($texts);
        }

        $readable = str_starts_with($mimeType, 'text/') || $mimeType === 'message/delivery-status';

        if (! $readable || (string) $part->getFilename() !== '') {
            return '';
        }

        return $this->readableText($part->getBody(), (string) $part->getFilename(), $attachment) ?? '';
    }

    /**
     * Decode a readable part's body.
     *
     * Gmail may store even a delivery status body as an attachment, leaving
     * only its id inline, so that is fetched. A part with a filename is a
     * real attachment and is never fetched. The client leaves the body null
     * when Gmail omits it, despite its docblock.
     *
     * @param  Closure(string): ?MessagePartBody  $attachment
     */
    private function readableText(?MessagePartBody $body, string $filename, Closure $attachment): ?string
    {
        $attachmentId = (string) $body?->getAttachmentId();

        if ((string) $body?->getData() === '' && $attachmentId !== '' && $filename === '') {
            $body = $attachment($attachmentId);
        }

        return $this->extractText($body);
    }

    /**
     * Join non-empty text pieces, one per line.
     *
     * @param  array<int, string>  $texts
     */
    private function joinText(array $texts): string
    {
        return implode("\n", array_filter($texts, fn (string $text): bool => trim($text) !== ''));
    }

    /**
     * Decode a base64url body part when present.
     */
    private function extractText(?MessagePartBody $body): ?string
    {
        $data = $body?->getData();

        if ($data === null || $data === '') {
            return null;
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
