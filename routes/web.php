<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Templates\TemplateAttachmentController;
use App\Livewire\Templates\BuilderPage;
use Illuminate\Support\Facades\Route;

foreach (config('landing-pages.domains', []) as $landingDomain) {
    if (! is_string($landingDomain) || $landingDomain === '') {
        continue;
    }

    Route::domain($landingDomain)->group(function (): void {
        Route::get('/', [LandingPageController::class, 'showForHost']);
    });
}

$landingWildcardRoot = config('landing-pages.wildcard_root');

if (is_string($landingWildcardRoot) && $landingWildcardRoot !== '') {
    Route::domain('{subdomain}.'.$landingWildcardRoot)->group(function (): void {
        Route::get('/', [LandingPageController::class, 'showForSubdomain']);
    });
}

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
    Route::livewire('users', 'pages::users.index')
        ->middleware('can:manage-users')
        ->name('users.index');
    Route::livewire('contacts', 'pages::contacts.index')->name('contacts.index');
    Route::livewire('contacts/groups/{group}', 'pages::contacts.group-detail')->name('contacts.groups.show');
    Route::livewire('broadcasts', 'pages::broadcasts.index')->name('broadcasts.index');
    Route::livewire('broadcasts/history', 'pages::broadcasts.history')->name('broadcasts.history');
    Route::livewire('webhooks/logs', 'pages::webhooks.logs')->name('webhooks.logs');
    Route::livewire('templates', 'pages::templates.index')->name('templates.index');
    Route::livewire('templates/create', BuilderPage::class)->name('templates.create');
    Route::livewire('templates/{template}/edit', BuilderPage::class)->name('templates.edit');
    Route::livewire('landing-pages', 'pages::landing-pages.index')->name('landing-pages.index');
    Route::livewire('landing-pages/create', 'pages::landing-pages.editor')->name('landing-pages.create');
    Route::livewire('landing-pages/{landingPage}/edit', 'pages::landing-pages.editor')->name('landing-pages.edit');

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
Route::get('events/{slug}', [LandingPageController::class, 'show'])->name('events.show');

// Legal Pages
Route::view('terms-of-service', 'legal.terms-of-service')->name('legal.tos');
Route::view('acceptable-use-policy', 'legal.acceptable-use-policy')->name('legal.aup');
Route::view('privacy-policy', 'legal.privacy-policy')->name('legal.privacy');

require __DIR__.'/settings.php';
