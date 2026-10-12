<?php

use App\Enums\SendFailureType;
use App\Exceptions\Mail\MailerAuthenticationException;
use App\Exceptions\Mail\MailerHttpException;
use App\Services\Mail\SendFailureClassifier;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Http\Client\ConnectionException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

/**
 * Build a Google API error the way google/apiclient reports it.
 */
function mmosGoogleError(int $status, string $reason, string $message = 'Provider error'): GoogleServiceException
{
    return new GoogleServiceException('{"error": {"code": '.$status.'}}', $status, null, [
        ['domain' => 'global', 'reason' => $reason, 'message' => $message],
    ]);
}

/**
 * Wrap an exception the way GoogleGmailApi does.
 */
function mmosGmailWrapped(Throwable $exception): RuntimeException
{
    return new RuntimeException('Gmail send failed: '.$exception->getMessage(), 0, $exception);
}

test('classifies send failures', function (Throwable $exception, SendFailureType $expected) {
    expect(app(SendFailureClassifier::class)->classify($exception))->toBe($expected);
})->with([
    'smtp 550 user unknown' => [
        fn () => new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 5.1.1 User unknown".', 550),
        SendFailureType::Recipient,
    ],
    'smtp 553 mailbox name not allowed' => [
        fn () => new UnexpectedResponseException('Expected response code "250/251/252" but got code "553".', 553),
        SendFailureType::Recipient,
    ],
    'smtp 554 after rcpt' => [
        fn () => new UnexpectedResponseException('Expected response code "250/251/252" but got code "554".', 554),
        SendFailureType::Recipient,
    ],
    'smtp 554 after data' => [
        fn () => new UnexpectedResponseException('Expected response code "250" but got code "554".', 554),
        SendFailureType::Transient,
    ],
    'smtp 550 sender rejected at mail from' => [
        fn () => new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 5.1.0 Sender address rejected".', 550),
        SendFailureType::Transient,
    ],
    'smtp 550 spam rejection after data' => [
        fn () => new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 5.7.1 Message rejected as spam".', 550),
        SendFailureType::Transient,
    ],
    'smtp 553 sender not owned at mail from' => [
        fn () => new UnexpectedResponseException('Expected response code "250" but got code "553", with message "553 5.7.1 Sender address rejected: not owned by user".', 553),
        SendFailureType::Transient,
    ],
    'smtp 554 relay denied at rcpt' => [
        fn () => new UnexpectedResponseException('Expected response code "250/251/252" but got code "554", with message "554 5.7.1 <ada@example.com>: Relay access denied".', 554),
        SendFailureType::Transient,
    ],
    'smtp 550 policy block at rcpt' => [
        fn () => new UnexpectedResponseException('Expected response code "250/251/252" but got code "550", with message "550 5.7.1 Service unavailable, client host blocked".', 550),
        SendFailureType::Transient,
    ],
    'smtp 451 local error' => [
        fn () => new UnexpectedResponseException('Expected response code "250" but got code "451".', 451),
        SendFailureType::Transient,
    ],
    'smtp 421 service unavailable' => [
        fn () => new UnexpectedResponseException('Expected response code "250" but got code "421".', 421),
        SendFailureType::Transient,
    ],
    'smtp timeout' => [
        fn () => new TransportException('Connection to "smtp.example.com:587" timed out.'),
        SendFailureType::Transient,
    ],
    'smtp could not connect' => [
        fn () => new TransportException('Connection could not be established with host "smtp.example.com:587".'),
        SendFailureType::Transient,
    ],
    'smtp 535 bad credentials' => [
        fn () => new TransportException('Failed to authenticate on SMTP server with username "user".', 535),
        SendFailureType::Connection,
    ],
    'smtp auth failure with a temporary code' => [
        fn () => new TransportException('Failed to authenticate on SMTP server with username "user".', 454),
        SendFailureType::Connection,
    ],
    'smtp 530 authentication required' => [
        fn () => new UnexpectedResponseException('Expected response code "250" but got code "530".', 530),
        SendFailureType::Connection,
    ],
    'gmail 429' => [fn () => mmosGmailWrapped(mmosGoogleError(429, 'rateLimitExceeded')), SendFailureType::Transient],
    'gmail 403 user rate limit' => [fn () => mmosGmailWrapped(mmosGoogleError(403, 'userRateLimitExceeded')), SendFailureType::Transient],
    'gmail 403 insufficient permissions' => [fn () => mmosGmailWrapped(mmosGoogleError(403, 'insufficientPermissions')), SendFailureType::Connection],
    'gmail 401' => [fn () => mmosGmailWrapped(mmosGoogleError(401, 'authError')), SendFailureType::Connection],
    'gmail 400 invalid recipient' => [fn () => mmosGmailWrapped(mmosGoogleError(400, 'invalidArgument', 'Invalid To header')), SendFailureType::Recipient],
    'gmail 400 invalid sender' => [fn () => mmosGmailWrapped(mmosGoogleError(400, 'invalidArgument', 'Invalid From header')), SendFailureType::Transient],
    'gmail 500' => [fn () => mmosGmailWrapped(mmosGoogleError(500, 'backendError')), SendFailureType::Transient],
    'graph 401' => [fn () => new MailerHttpException('Outlook message send failed.', 401, 'InvalidAuthenticationToken'), SendFailureType::Connection],
    'graph 403' => [fn () => new MailerHttpException('Outlook message send failed.', 403, 'ErrorAccessDenied'), SendFailureType::Connection],
    'graph 429' => [fn () => new MailerHttpException('Outlook message send failed.', 429, 'ApplicationThrottled'), SendFailureType::Transient],
    'graph 503' => [fn () => new MailerHttpException('Outlook message send failed.', 503, null), SendFailureType::Transient],
    'graph 400 invalid recipients' => [fn () => new MailerHttpException('Outlook message send failed.', 400, 'ErrorInvalidRecipients'), SendFailureType::Recipient],
    'oauth refresh invalid grant' => [fn () => new MailerHttpException('Google token refresh failed.', 400, 'invalid_grant'), SendFailureType::Connection],
    'oauth token missing' => [fn () => new MailerAuthenticationException('Mailer connection is missing an access token.'), SendFailureType::Connection],
    'http connection error' => [fn () => new ConnectionException('cURL error 28: Operation timed out'), SendFailureType::Transient],
    'unknown exception' => [fn () => new RuntimeException('Something unexpected'), SendFailureType::Transient],
]);
