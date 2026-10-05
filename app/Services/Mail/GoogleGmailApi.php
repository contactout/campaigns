<?php

namespace App\Services\Mail;

use App\Contracts\Mail\GmailApi;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use RuntimeException;
use Throwable;

/**
 * Gmail API adapter backed by google/apiclient.
 */
class GoogleGmailApi implements GmailApi
{
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
     * List message ids matching the given query.
     *
     * @return array<int, string>
     */
    public function listMessageIds(string $accessToken, string $query, int $maxResults = 50): array
    {
        try {
            $list = $this->gmail($accessToken)->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => $maxResults,
            ]);
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

        return $ids;
    }

    /**
     * Fetch headers and body text for a single message.
     *
     * @return array{messageId: string, inReplyTo: string|null, references: array<int, string>, fromEmail: string|null, subject: string, text: string|null}
     */
    public function getMessage(string $accessToken, string $messageId): array
    {
        try {
            $message = $this->gmail($accessToken)->users_messages->get('me', $messageId, [
                'format' => 'full',
            ]);
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

        return [
            'messageId' => $headers['message-id'] ?? $messageId,
            'inReplyTo' => $inReplyTo !== '' ? $inReplyTo : null,
            'references' => $references,
            'fromEmail' => $this->extractEmail($headers['from'] ?? null),
            'subject' => $headers['subject'] ?? '',
            'text' => $this->extractText($message->getPayload()?->getBody()?->getData())
                ?? $message->getSnippet(),
        ];
    }

    /**
     * Build an authenticated Gmail service client.
     */
    private function gmail(string $accessToken): Gmail
    {
        $client = new GoogleClient;
        $client->setAccessToken(['access_token' => $accessToken]);

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
     * Decode a base64url body part when present.
     */
    private function extractText(?string $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
