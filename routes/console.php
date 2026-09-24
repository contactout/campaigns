<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping();

Schedule::command('campaigns:check-mailboxes')->everyTenMinutes()->withoutOverlapping();

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');
