<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'Draft';
    case Active = 'Active';
    case Stopped = 'Stopped';
    case Completed = 'Completed';
    case Archived = 'Archived';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
