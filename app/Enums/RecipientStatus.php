<?php

namespace App\Enums;

enum RecipientStatus: string
{
    case Active = 'Active';
    case Pending = 'Pending';
    case Bounced = 'Bounced';
    case Completed = 'Completed';
    case Error = 'Error';
    case Paused = 'Paused';
    case Replied = 'Replied';
    case Unsubscribed = 'Unsubscribed';
    case Archived = 'Archived';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
