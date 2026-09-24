<?php

namespace App\Enums;

enum ContactIdentityType: string
{
    case Email = 'email';
    case Phone = 'phone';

    /**
     * Get the display label for the identity type.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Normalize a raw value for storage and uniqueness comparison.
     */
    public function normalize(string $value): string
    {
        return match ($this) {
            self::Email => mb_strtolower(trim($value)),
            self::Phone => preg_replace('/\D+/', '', $value) ?? '',
        };
    }
}
