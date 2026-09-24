<?php

namespace App\Enums;

enum PlaceholderType: string
{
    case Text = 'Text';
    case Number = 'Number';
    case Date = 'Date';

    /**
     * Get the display label for the placeholder type.
     */
    public function label(): string
    {
        return $this->value;
    }
}
