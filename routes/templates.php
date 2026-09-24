<?php

use App\Http\Controllers\Templates\PlaceholderController;
use App\Http\Controllers\Templates\SignatureController;
use App\Http\Controllers\Templates\TemplateController;
use App\Http\Controllers\Templates\TemplateFolderController;
use Illuminate\Support\Facades\Route;

Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
Route::get('templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
Route::patch('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

Route::post('folders', [TemplateFolderController::class, 'store'])->name('folders.store');
Route::patch('folders/{folder}', [TemplateFolderController::class, 'update'])->name('folders.update');
Route::delete('folders/{folder}', [TemplateFolderController::class, 'destroy'])->name('folders.destroy');

Route::get('signatures', [SignatureController::class, 'index'])->name('signatures.index');
Route::post('signatures', [SignatureController::class, 'store'])->name('signatures.store');
Route::patch('signatures/{signature}', [SignatureController::class, 'update'])->name('signatures.update');
Route::delete('signatures/{signature}', [SignatureController::class, 'destroy'])->name('signatures.destroy');

Route::post('templates/{template}/placeholders', [PlaceholderController::class, 'store'])->name('placeholders.store');
Route::patch('placeholders/{placeholder}', [PlaceholderController::class, 'update'])->name('placeholders.update');
Route::delete('placeholders/{placeholder}', [PlaceholderController::class, 'destroy'])->name('placeholders.destroy');
