<?php

namespace App\Contracts\Mail;

/**
 * Thin Gmail API surface used by campaign mailers and verifiers.
 *
 * Kept narrow so tests can fake sends and profile lookups without mocking the
 * full Google client.
 */
interface GmailApi
{
    /**
     * Send a raw RFC822 message and return the provider ids.
     *
     * When a thread id is given the message is filed into that Gmail
     * conversation instead of starting a new one.
     *
     * @return array{id: string, threadId: string}
     *
     * @throws \RuntimeException When the API rejects the send.
     */
    public function sendRaw(string $accessToken, string $raw, ?string $threadId = null): array;

    /**
     * Fetch the authenticated user's Gmail profile email address.
     *
     * @throws \RuntimeException When the API rejects the request.
     */
    public function getProfileEmail(string $accessToken): string;

    /**
     * List message ids matching the given query.
     *
     * @return array<int, string>
     *
     * @throws \RuntimeException When the API rejects the request.
     */
    public function listMessageIds(string $accessToken, string $query, int $maxResults = 50): array;

    /**
     * Fetch headers and body text for a single message.
     *
     * @return array{messageId: string, inReplyTo: string|null, references: array<int, string>, fromEmail: string|null, subject: string, text: string|null}
     *
     * @throws \RuntimeException When the API rejects the request.
     */
    public function getMessage(string $accessToken, string $messageId): array;
}
