<?php

use Illuminate\Support\Facades\Route;

// Stripe Webhook Route (must be before catch-all)
Route::post('/stripe/webhook', [\App\Http\Controllers\WebhookController::class, 'handleWebhook']);

Route::get('{any?}', function () {
    return view('application');
})->where('any', '.*');
