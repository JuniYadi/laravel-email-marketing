<?php

use App\Http\Controllers\Webhooks\SnsWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/sns', SnsWebhookController::class)->name('webhooks.sns');
