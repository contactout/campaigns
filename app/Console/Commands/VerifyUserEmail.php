<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campaigns:verify-user {email : Email address of the user to verify}')]
#[Description('Mark a user\'s email address as verified (for installs without system mail)')]
class VerifyUserEmail extends Command
{
    /**
     * Mark the given user's email address as verified.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info("{$email} is already verified.");

            return self::SUCCESS;
        }

        $user->markEmailAsVerified();

        $this->info("Marked {$email} as verified.");

        return self::SUCCESS;
    }
}
