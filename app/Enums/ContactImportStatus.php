<?php

namespace App\Enums;

enum ContactImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get the display label for the import status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Determine whether the import has stopped running.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
