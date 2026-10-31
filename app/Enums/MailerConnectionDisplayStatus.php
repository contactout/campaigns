<?php

namespace App\Enums;

/**
 * The status a connection is shown with, derived from its stored status and
 * whether a verification/runtime exception is recorded.
 *
 * The connections table and the dashboard's "needs attention" count both read
 * this enum, so the pill a user sees and the count they are sent to fix cannot
 * disagree: a row shows Verified exactly when it is not counted as needing
 * attention. An Active connection with an exception is Failed, not Verified.
 */
enum MailerConnectionDisplayStatus: string
{
    case Verified = 'Verified';
    case Failed = 'Failed';
    case Pending = 'Pending';
    case Deactivated = 'Deactivated';
    case Disconnected = 'Disconnected';

    /**
     * Derive the display status from the stored status and exception flag.
     */
    public static function derive(MailerConnectionStatus $status, bool $hasException): self
    {
        if ($hasException) {
            return self::Failed;
        }

        return match ($status) {
            MailerConnectionStatus::Active => self::Verified,
            MailerConnectionStatus::Pending => self::Pending,
            MailerConnectionStatus::Deactivated => self::Deactivated,
            MailerConnectionStatus::Disconnected => self::Disconnected,
        };
    }

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return $this->value;
    }
}
