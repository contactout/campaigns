<?php

namespace App\Services\Mail;

use App\Enums\SendFailureType;
use App\Exceptions\Mail\MailerAuthenticationException;
use App\Exceptions\Mail\MailerHttpException;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Http\Client\ConnectionException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Decides whether a send failure is the recipient's, temporary, or the connection's.
 *
 * The exception and each of its `getPrevious()` causes are inspected in turn,
 * outermost first, and the first one that is recognised decides the result.
 * Anything unrecognised is treated as transient: retrying a few times is
 * cheaper than bouncing a good address or deactivating a working connection,
 * and the caller caps the number of retries.
 */
class SendFailureClassifier
{
    /**
     * SMTP reply codes that reject the recipient's mailbox when they answer RCPT TO.
     *
     * @var array<int, int>
     */
    private const array SMTP_RECIPIENT_CODES = [550, 551, 553, 554];

    /**
     * How Symfony Mailer names the codes RCPT TO expects; no other command expects them.
     */
    private const string SMTP_RCPT_EXPECTED = '"250/251/252"';

    /**
     * SMTP reply codes that reject the connection's credentials.
     *
     * @var array<int, int>
     */
    private const array SMTP_CONNECTION_CODES = [530, 534, 535];

    /**
     * Provider reasons that mark a 403 as throttling rather than a permission error.
     *
     * @var array<int, string>
     */
    private const array RATE_LIMIT_REASONS = ['rateLimitExceeded', 'userRateLimitExceeded', 'dailyLimitExceeded'];

    /**
     * Provider error codes that reject the recipient on a 400.
     *
     * @var array<int, string>
     */
    private const array INVALID_RECIPIENT_CODES = ['ErrorInvalidRecipients', 'ErrorInvalidRecipient'];

    /**
     * OAuth token endpoint errors that mean the grant can no longer be used.
     *
     * @var array<int, string>
     */
    private const array OAUTH_GRANT_ERRORS = ['invalid_grant', 'invalid_client', 'unauthorized_client'];

    /**
     * Classify the exception thrown while sending.
     */
    public function classify(Throwable $exception): SendFailureType
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $type = $this->classifyOne($current);

            if ($type !== null) {
                return $type;
            }
        }

        return SendFailureType::Transient;
    }

    /**
     * Classify a single exception, or return null when it is not recognised.
     */
    private function classifyOne(Throwable $exception): ?SendFailureType
    {
        return match (true) {
            $exception instanceof MailerAuthenticationException => SendFailureType::Connection,
            $exception instanceof MailerHttpException => $this->classifyHttp(
                $exception->status,
                $exception->errorCode,
                [],
                in_array($exception->errorCode, self::INVALID_RECIPIENT_CODES, true),
            ),
            $exception instanceof GoogleServiceException => $this->classifyHttp(
                (int) $exception->getCode(),
                null,
                $this->googleReasons($exception),
                $this->googleRejectsRecipient($exception),
            ),
            $exception instanceof TransportExceptionInterface => $this->classifySmtp($exception),
            $exception instanceof ConnectionException => SendFailureType::Transient,
            default => null,
        };
    }

    /**
     * Classify a Symfony Mailer transport failure by its SMTP reply code.
     */
    private function classifySmtp(TransportExceptionInterface $exception): SendFailureType
    {
        $code = (int) $exception->getCode();

        // Symfony reports every failed login this way, whatever reply code the
        // server used.
        if (str_contains($exception->getMessage(), 'Failed to authenticate')
            || in_array($code, self::SMTP_CONNECTION_CODES, true)) {
            return SendFailureType::Connection;
        }

        // A 5xx only says the address is bad when it answers RCPT TO. The same
        // codes after MAIL FROM or DATA reject the sender or the content, and
        // a 5.7.x or relay refusal at RCPT TO is a policy on the sender; those
        // would bounce every contact in turn, so they are retried and capped.
        if (in_array($code, self::SMTP_RECIPIENT_CODES, true)
            && str_contains($exception->getMessage(), self::SMTP_RCPT_EXPECTED)
            && ! $this->isSmtpPolicyRejection($exception->getMessage())) {
            return SendFailureType::Recipient;
        }

        // 4xx, timeouts and dropped connections (code 0), and any other 5xx.
        return SendFailureType::Transient;
    }

    /**
     * Determine whether an SMTP reply is a policy refusal rather than an unknown mailbox.
     */
    private function isSmtpPolicyRejection(string $message): bool
    {
        return preg_match('/\b5\.7\.\d{1,3}\b|relay/i', $message) === 1;
    }

    /**
     * Classify a Gmail or Graph HTTP failure.
     *
     * @param  array<int, string>  $reasons
     */
    private function classifyHttp(int $status, ?string $errorCode, array $reasons, bool $rejectsRecipient): ?SendFailureType
    {
        if ($status === 400) {
            if (in_array($errorCode, self::OAUTH_GRANT_ERRORS, true)) {
                return SendFailureType::Connection;
            }

            if ($rejectsRecipient) {
                return SendFailureType::Recipient;
            }

            return SendFailureType::Transient;
        }

        if ($status === 401) {
            return SendFailureType::Connection;
        }

        if ($status === 403) {
            return array_intersect($reasons, self::RATE_LIMIT_REASONS) !== []
                ? SendFailureType::Transient
                : SendFailureType::Connection;
        }

        // 429, 5xx, and code 0 from a request that never got a response.
        return $status === 0 ? null : SendFailureType::Transient;
    }

    /**
     * Read the `reason` of each error in a Google API error response.
     *
     * @return array<int, string>
     */
    private function googleReasons(GoogleServiceException $exception): array
    {
        $reasons = [];

        foreach ($exception->getErrors() ?? [] as $error) {
            if (is_string($error['reason'] ?? null)) {
                $reasons[] = $error['reason'];
            }
        }

        return $reasons;
    }

    /**
     * Determine whether a Google API error rejects the recipient address.
     *
     * Gmail answers an unusable address with `invalidArgument` and "Invalid To
     * header"; the same reason for any other argument (the sender, the thread)
     * is not about the recipient.
     */
    private function googleRejectsRecipient(GoogleServiceException $exception): bool
    {
        foreach ($exception->getErrors() ?? [] as $error) {
            if (($error['reason'] ?? null) === 'invalidArgument'
                && preg_match('/\b(to|cc|bcc) header\b|recipient/i', (string) ($error['message'] ?? '')) === 1) {
                return true;
            }
        }

        return false;
    }
}
