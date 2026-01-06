<?php

use Illuminate\Support\Facades\Route;

// Stripe Webhook Route (must be before catch-all)
Route::post('/stripe/webhook', [\App\Http\Controllers\WebhookController::class, 'handleWebhook']);

// Named login route (required for auth middleware redirects)
Route::get('/login', function () {
    return view('application');
})->name('login');

Route::get('{any?}', function () {
    return view('application');
})->where('any', '.*');
