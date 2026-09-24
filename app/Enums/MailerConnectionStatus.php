<?php

namespace App\Enums;

enum MailerConnectionStatus: string
{
    case Pending = 'Pending';
    case Active = 'Active';
    case Deactivated = 'Deactivated';
    case Disconnected = 'Disconnected';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
