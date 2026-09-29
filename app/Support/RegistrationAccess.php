<?php

namespace App\Support;

use App\Models\TeamInvitation;
use App\Models\User;

class RegistrationAccess
{
    /**
     * Whether registration is open to everyone (no invitation needed).
     */
    public function isOpen(): bool
    {
        return config('app.registration_enabled') || ! User::query()->exists();
    }

    /**
     * Whether the request may register, given an optional invitation code
     * and, when known, the email address being registered.
     */
    public function allows(mixed $invitationCode = null, ?string $email = null): bool
    {
        return $this->isOpen() || $this->hasValidInvitation($invitationCode, $email);
    }

    private function hasValidInvitation(mixed $invitationCode, ?string $email): bool
    {
        if (! is_string($invitationCode) || $invitationCode === '') {
            return false;
        }

        $invitation = TeamInvitation::query()
            ->where('code', $invitationCode)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->first();

        if (! $invitation) {
            return false;
        }

        return $email === null || strtolower($invitation->email) === strtolower($email);
    }
}
