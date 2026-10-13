<?php

use App\Http\Controllers\Tracking\TrackingController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public tracking + unsubscribe routes
|--------------------------------------------------------------------------
|
| These routes are intentionally registered outside the authenticated
| `{current_team}` group. Open/click are hit by mail clients and unsubscribe
| links are validated by a signed URL rather than a session.
|
*/

Route::get('t/o/{campaignEmail:tracker}', [TrackingController::class, 'open'])->name('tracking.open');
Route::get('t/c/{hash}', [TrackingController::class, 'click'])->name('tracking.click');

Route::get('unsubscribe/done', fn () => Inertia::render('unsubscribed'))->name('unsubscribe.done');

Route::middleware('signed')->group(function () {
    Route::get('unsubscribe/{recipient}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
    // Mail providers POST RFC 8058 one-click unsubscribes without a CSRF
    // token; the route is protected by its signed URL instead.
    Route::post('unsubscribe/{recipient}', [UnsubscribeController::class, 'store'])
        ->withoutMiddleware(PreventRequestForgery::class)
        ->name('unsubscribe.store');
});
