<?php

use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\ContactListController;
use Illuminate\Support\Facades\Route;

Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

Route::get('lists', [ContactListController::class, 'index'])->name('lists.index');
Route::post('lists', [ContactListController::class, 'store'])->name('lists.store');
Route::get('lists/{list}', [ContactListController::class, 'show'])->name('lists.show');
Route::patch('lists/{list}', [ContactListController::class, 'update'])->name('lists.update');
Route::delete('lists/{list}', [ContactListController::class, 'destroy'])->name('lists.destroy');
Route::post('lists/{list}/contacts', [ContactListController::class, 'attachContacts'])->name('lists.contacts.attach');
Route::delete('lists/{list}/contacts/{contact}', [ContactListController::class, 'detachContact'])->name('lists.contacts.detach');
