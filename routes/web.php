<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Templates\TemplateAttachmentController;
use App\Livewire\Templates\BuilderPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Google OAuth Routes
Route::middleware(['guest', 'auth.mode'])->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::livewire('dashboard', 'pages::dashboard.index')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('contacts', 'pages::contacts.index')->name('contacts.index');
    Route::livewire('contacts/groups/{group}', 'pages::contacts.group-detail')->name('contacts.groups.show');
    Route::livewire('broadcasts', 'pages::broadcasts.index')->name('broadcasts.index');
    Route::livewire('broadcasts/history', 'pages::broadcasts.history')->name('broadcasts.history');
    Route::livewire('templates', 'pages::templates.index')->name('templates.index');
    Route::livewire('templates/create', BuilderPage::class)->name('templates.create');
    Route::livewire('templates/{template}/edit', BuilderPage::class)->name('templates.edit');

    Route::prefix('templates/attachments')->name('templates.attachments.')->group(function () {
        Route::post('presign', [TemplateAttachmentController::class, 'presign'])->name('presign');
        Route::post('finalize', [TemplateAttachmentController::class, 'finalize'])->name('finalize');
        Route::delete('', [TemplateAttachmentController::class, 'delete'])->name('delete');
        Route::post('cleanup-unsaved', [TemplateAttachmentController::class, 'cleanupUnsaved'])->name('cleanup-unsaved');
    });
});

Route::livewire('unsubscribe/{contact}', 'pages::unsubscribe.show')
    ->name('unsubscribe')
    ->middleware('signed');

// Legal Pages
Route::view('terms-of-service', 'legal.terms-of-service')->name('legal.tos');
Route::view('acceptable-use-policy', 'legal.acceptable-use-policy')->name('legal.aup');
Route::view('privacy-policy', 'legal.privacy-policy')->name('legal.privacy');

require __DIR__.'/settings.php';
