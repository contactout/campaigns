<?php

use App\Http\Controllers\Campaigns\CampaignController;
use App\Http\Controllers\Campaigns\CampaignStepController;
use Illuminate\Support\Facades\Route;

Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
Route::patch('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

Route::post('campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start');
Route::post('campaigns/{campaign}/stop', [CampaignController::class, 'stop'])->name('campaigns.stop');
Route::post('campaigns/{campaign}/archive', [CampaignController::class, 'archive'])->name('campaigns.archive');
Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');

Route::post('campaigns/{campaign}/steps', [CampaignStepController::class, 'store'])->name('campaigns.steps.store');
Route::patch('campaigns/{campaign}/steps/{step}', [CampaignStepController::class, 'update'])->name('campaigns.steps.update');
Route::delete('campaigns/{campaign}/steps/{step}', [CampaignStepController::class, 'destroy'])->name('campaigns.steps.destroy');
Route::post('campaigns/{campaign}/steps/reorder', [CampaignStepController::class, 'reorder'])->name('campaigns.steps.reorder');
