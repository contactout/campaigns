<?php

namespace App\Services\Mail;

use App\Concerns\AppliesThreadHeaders;
use App\Data\EmailThread;
use App\Models\MailerConnection;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Builds the same HTML and plain-text alternatives for every campaign sender.
 */
class CampaignMessageBuilder
{
    use AppliesThreadHeaders;

    /**
     * @param  array<string, string>  $headers
     */
    public function build(MailerConnection $connection, string $to, string $subject, string $html, array $headers = [], ?EmailThread $thread = null, ?string $toName = null): Email
    {
        $settings = $connection->smtp_setting ?? [];

        $email = (new Email)
            ->from(new Address(
                (string) ($settings['from_email'] ?? $settings['email'] ?? ''),
                (string) ($settings['from_name'] ?? $settings['name'] ?? ''),
            ))
            ->to(new Address($to, (string) $toName))
            ->subject($subject)
            ->text($this->plainText($html))
            ->html($html);

        foreach ($headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        $this->applyThreadHeaders($email, $thread);

        return $email;
    }

    /**
     * Keep anchor destinations (including unsubscribe), but not image sources.
     */
    private function plainText(string $html): string
    {
        $document = HTMLDocument::createFromString($html !== '' ? $html : '<body></body>', LIBXML_NOERROR, 'UTF-8');
        $text = $this->nodeText($document);
        $text = (string) preg_replace('/[\t ]+\n/', "\n", $text);

        return trim((string) preg_replace('/\n{3,}/', "\n\n", $text));
    }

    private function nodeText(Node $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return (string) preg_replace('/\s+/u', ' ', $node->textContent);
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, ['head', 'script', 'style', 'img'], true)) {
            return '';
        }

        if ($tag === 'br') {
            return "\n";
        }

        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= $this->nodeText($child);
        }

        if ($node instanceof Element && $tag === 'a') {
            $href = trim($node->getAttribute('href') ?? '');

            if ($href !== '' && trim($text) !== $href) {
                $text .= ' <'.$href.'>';
            }
        }

        if (in_array($tag, ['p', 'div', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'ul', 'ol', 'table'], true)) {
            return "\n".trim($text)."\n";
        }

        if (in_array($tag, ['td', 'th'], true)) {
            return $text.' ';
        }

        return $text;
    }
}
