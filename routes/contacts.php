<?php

use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\ContactFieldController;
use App\Http\Controllers\Contacts\ContactListController;
use Illuminate\Support\Facades\Route;

Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
Route::patch('contacts/{contact}/cell', [ContactController::class, 'cell'])->name('contacts.cell');
Route::patch('contacts/{contact}/property', [ContactController::class, 'property'])->name('contacts.property');
Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

Route::post('contact-fields', [ContactFieldController::class, 'store'])->name('contact-fields.store');
Route::patch('contact-fields/{field}', [ContactFieldController::class, 'update'])->name('contact-fields.update');
Route::delete('contact-fields/{field}', [ContactFieldController::class, 'destroy'])->name('contact-fields.destroy');

Route::post('lists', [ContactListController::class, 'store'])->name('lists.store');
Route::patch('lists/{list}', [ContactListController::class, 'update'])->name('lists.update');
Route::delete('lists/{list}', [ContactListController::class, 'destroy'])->name('lists.destroy');
Route::post('lists/{list}/contacts', [ContactListController::class, 'attachContacts'])->name('lists.contacts.attach');
Route::delete('lists/{list}/contacts/{contact}', [ContactListController::class, 'detachContact'])->name('lists.contacts.detach');
