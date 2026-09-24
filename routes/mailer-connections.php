<?php

use App\Http\Controllers\MailerConnections\MailerConnectionController;
use App\Http\Controllers\MailerConnections\OAuthRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('mailer-connections', [MailerConnectionController::class, 'index'])->name('mailer-connections.index');
Route::post('mailer-connections', [MailerConnectionController::class, 'store'])->name('mailer-connections.store');
Route::patch('mailer-connections/{mailerConnection}', [MailerConnectionController::class, 'update'])->name('mailer-connections.update');
Route::delete('mailer-connections/{mailerConnection}', [MailerConnectionController::class, 'destroy'])->name('mailer-connections.destroy');
Route::post('mailer-connections/{mailerConnection}/verify', [MailerConnectionController::class, 'verify'])->name('mailer-connections.verify');
Route::get('mailer-connections/oauth/{provider}', OAuthRedirectController::class)
    ->whereIn('provider', ['gmail', 'outlook'])
    ->name('mailer-connections.oauth.redirect');
