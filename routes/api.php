<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user()->load('subscription.plan');
})->middleware('auth:sanctum');

// User Billing & Plans
Route::middleware('auth:sanctum')->get('/user/billing', [\App\Http\Controllers\User\BillingController::class, 'index']);
Route::middleware('auth:sanctum')->get('/user/plans', [\App\Http\Controllers\User\PlanController::class, 'index']);
Route::middleware('auth:sanctum')->post('/user/subscribe', [\App\Http\Controllers\User\SubscriptionController::class, 'subscribe']);

// Stripe Checkout
Route::middleware('auth:sanctum')->post('/stripe/checkout', [\App\Http\Controllers\StripeController::class, 'checkout']);

// Auth Group
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('profile-update', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('2fa-send', [\App\Http\Controllers\Auth\TwoFactorController::class, 'send']);
        Route::post('2fa-verify', [\App\Http\Controllers\Auth\TwoFactorController::class, 'verify']);
    });
});

// Admin Group
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('members', [\App\Http\Controllers\Admin\MemberController::class, 'index']);
    Route::delete('members/{id}', [\App\Http\Controllers\Admin\MemberController::class, 'destroy']);

    // Plan Management
    Route::get('plans', [\App\Http\Controllers\Admin\PlanController::class, 'index']);
    Route::post('plans', [\App\Http\Controllers\Admin\PlanController::class, 'store']);
    Route::put('plans/{id}', [\App\Http\Controllers\Admin\PlanController::class, 'update']);
    Route::delete('plans/{id}', [\App\Http\Controllers\Admin\PlanController::class, 'destroy']);

    // Global Settings
    Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'index']);
    Route::patch('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update']);
    Route::post('settings/test-email', [\App\Http\Controllers\Admin\SettingController::class, 'testEmail']);
});
