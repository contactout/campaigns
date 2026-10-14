<?php

namespace App\Rules;

use App\Models\TeamInvitation;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTeamInvitation implements ValidationRule
{
    public function __construct(protected ?User $user, protected bool $requireAssignableRole = false)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof TeamInvitation || ! $this->user instanceof User) {
            $fail(__('This invitation was sent to a different email address.'));

            return;
        }

        if ($value->isAccepted()) {
            $fail(__('This invitation has already been accepted.'));

            return;
        }

        if ($value->isExpired()) {
            $fail(__('This invitation has expired.'));

            return;
        }

        if (strtolower($value->email) !== strtolower($this->user->email)) {
            $fail(__('This invitation was sent to a different email address.'));

            return;
        }

        if ($this->requireAssignableRole && ! $value->role->isAssignable()) {
            $fail(__('This invitation grants a role that can no longer be assigned. Ask the team for a new invitation.'));
        }
    }
}
