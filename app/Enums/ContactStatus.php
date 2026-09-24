<?php

namespace App\Enums;

enum ContactStatus: string
{
    case NotContacted = 'NotContacted';
    case Contacted = 'Contacted';
    case Replied = 'Replied';
    case Bounced = 'Bounced';
    case Unsubscribed = 'Unsubscribed';
    case DoNotContact = 'DoNotContact';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
