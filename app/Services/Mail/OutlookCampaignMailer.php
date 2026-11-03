<?php

namespace App\Services\Mail;

use App\Contracts\Mail\CampaignMailer;
use App\Data\EmailThread;
use App\Data\SendResult;
use App\Enums\MailerType;
use App\Exceptions\Mail\MailerHttpException;
use App\Models\MailerConnection;
use App\Services\OAuth\OAuthTokenManager;
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
     * @throws MailerHttpException When Graph rejects the send.
     */
    public function send(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null): SendResult
    {
        if ($connection->mailer_type !== MailerType::Outlook) {
            throw new RuntimeException('OutlookCampaignMailer requires an Outlook connection.');
        }

        $accessToken = $this->tokens->accessToken($connection);
        $message = (new CampaignMessageBuilder)->build($connection, $to, $subject, $html, $headers, $thread);

        // Graph only documents MIME bodies for new messages, so replies are
        // new messages carrying In-Reply-To/References headers.
        $id = $this->createMessage($accessToken, $message->toString());

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
     * Create a draft from MIME so Graph keeps both alternatives.
     */
    private function createMessage(string $accessToken, string $mime): string
    {
        $create = Http::withToken($accessToken)
            ->withBody(base64_encode($mime), 'text/plain')
            ->post('https://graph.microsoft.com/v1.0/me/messages');

        if (! $create->successful()) {
            throw MailerHttpException::fromResponse('Outlook message create failed.', $create);
        }

        $created = $create->json();
        $id = is_array($created) ? (string) ($created['id'] ?? '') : '';

        if ($id === '') {
            throw new RuntimeException('Outlook message create returned no id.');
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
            throw MailerHttpException::fromResponse('Outlook message send failed.', $send);
        }
    }
}
