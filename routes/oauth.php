<?php

use App\Http\Controllers\MailerConnections\OAuthCallbackController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('oauth/google/callback', [OAuthCallbackController::class, 'google'])
        ->name('mailer-connections.oauth.google.callback');

    Route::get('oauth/microsoft/callback', [OAuthCallbackController::class, 'microsoft'])
        ->name('mailer-connections.oauth.microsoft.callback');
});
