<?php

namespace App\Exceptions\Mail;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A provider HTTP API rejected a mail or OAuth request.
 *
 * Carries the HTTP status (also exposed as the exception code) and the
 * provider's error code so send failures can be classified.
 */
class MailerHttpException extends RuntimeException
{
    /**
     * Create a new mailer HTTP exception instance.
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Build the exception from a failed response.
     *
     * Reads Graph's `error.code` or the OAuth token endpoint's `error` string.
     */
    public static function fromResponse(string $message, Response $response): self
    {
        $payload = $response->json();
        $error = is_array($payload) ? ($payload['error'] ?? null) : null;

        $errorCode = match (true) {
            is_array($error) && is_string($error['code'] ?? null) => $error['code'],
            is_string($error) => $error,
            default => null,
        };

        return new self($message, $response->status(), $errorCode);
    }
}
