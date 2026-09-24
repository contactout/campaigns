<?php

namespace App\Enums;

enum MailerType: string
{
    case Smtp = 'Smtp';
    case Gmail = 'Gmail';
    case Outlook = 'Outlook';

    /**
     * Get the display label for the mailer type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::Gmail => 'Gmail',
            self::Outlook => 'Outlook',
        };
    }
}
