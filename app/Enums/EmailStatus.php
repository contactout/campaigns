<?php

namespace App\Enums;

enum EmailStatus: string
{
    case Pending = 'Pending';
    case Scheduled = 'Scheduled';
    case Sent = 'Sent';
    case Delivered = 'Delivered';
    case Opened = 'Opened';
    case Replied = 'Replied';
    case Bounced = 'Bounced';
    case Failed = 'Failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
