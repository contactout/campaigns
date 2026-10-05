<?php

namespace App\Services\Mail;

use App\Concerns\AppliesThreadHeaders;
use App\Contracts\Mail\CampaignMailer;
use App\Contracts\Mail\GmailApi;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Enums\MailerType;
use App\Models\MailerConnection;
use App\Services\OAuth\OAuthTokenManager;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends campaign emails through the Gmail API.
 *
 * Follow-ups are filed into the previous Gmail thread so they arrive as part
 * of the same conversation, and also carry the `In-Reply-To`/`References`
 * headers for recipients whose client threads on headers.
 */
class GmailCampaignMailer implements CampaignMailer
{
    use AppliesThreadHeaders;

    /**
     * Create a new Gmail campaign mailer instance.
     */
    public function __construct(
        private readonly OAuthTokenManager $tokens,
        private readonly GmailApi $gmail,
    ) {}

    /**
     * Send an HTML email using the connection's Gmail OAuth credentials.
     *
     * @param  array<string, string>  $headers
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
    {
        if ($connection->mailer_type !== MailerType::Gmail) {
            throw new RuntimeException('GmailCampaignMailer requires a Gmail connection.');
        }

        $settings = $connection->smtp_setting ?? [];
        $accessToken = $this->tokens->accessToken($connection);

        $email = (new Email)
            ->from(new Address(
                (string) ($settings['from_email'] ?? $settings['email'] ?? ''),
                (string) ($settings['from_name'] ?? $settings['name'] ?? ''),
            ))
            ->to($to)
            ->subject($subject)
            ->html($html);

        foreach ($headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        $this->applyThreadHeaders($email, $thread);

        // Symfony generates a Message-ID into a prepared copy of the headers,
        // which would leave the id unavailable after sending. Set it here so it
        // can be stored on the email for reply matching.
        if (! $email->getHeaders()->has('Message-ID')) {
            $email->getHeaders()->addIdHeader('Message-ID', $email->generateMessageId());
        }

        $raw = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');

        try {
            $sent = $this->gmail->sendRaw($accessToken, $raw, $thread?->threadId);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new RuntimeException('Gmail authentication or send failed.', 0, $exception);
        }

        $messageId = $email->getHeaders()->get('Message-ID')?->getBodyAsString();

        return new SendResult(
            messageId: $messageId !== null && $messageId !== '' ? $messageId : null,
            threadId: $sent['threadId'] !== '' ? $sent['threadId'] : null,
        );
    }
}
