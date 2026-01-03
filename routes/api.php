<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    $user = $request->user()->load('subscription.plan');

    // Add flag to check if user has ever used free trial
    $user->has_used_free_trial = \App\Models\Subscription::where('user_id', $user->id)
        ->where('stripe_status', 'free')
        ->exists();

    // Append subscription summary (selective for performance)
    $user->append('subscription_summary');

    return $user;
})->middleware('auth:sanctum');

// User Billing & Plans
Route::middleware('auth:sanctum')->get('/user/billing', [\App\Http\Controllers\User\BillingController::class, 'index']);
Route::middleware('auth:sanctum')->get('/user/plans', [\App\Http\Controllers\User\PlanController::class, 'index']);
Route::middleware('auth:sanctum')->post('/user/subscribe', [\App\Http\Controllers\User\SubscriptionController::class, 'subscribe']);
Route::middleware('auth:sanctum')->post('/user/subscription/cancel', [\App\Http\Controllers\User\SubscriptionController::class, 'cancel']);
Route::middleware('auth:sanctum')->post('/user/subscription/resume', [\App\Http\Controllers\User\SubscriptionController::class, 'resume']);

// Stripe Checkout
Route::middleware('auth:sanctum')->post('/stripe/checkout', [\App\Http\Controllers\StripeController::class, 'checkout']);

// Payment Methods Management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payment-methods', [\App\Http\Controllers\StripeController::class, 'listPaymentMethods']);
    Route::post('/payment-methods/setup-intent', [\App\Http\Controllers\StripeController::class, 'createSetupIntent']);
    Route::post('/payment-methods/{pmId}/set-default', [\App\Http\Controllers\StripeController::class, 'setDefaultPaymentMethod']);
    Route::delete('/payment-methods/{pmId}', [\App\Http\Controllers\StripeController::class, 'deletePaymentMethod']);
});

// Billing Address
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/billing-address', [\App\Http\Controllers\StripeController::class, 'getBillingAddress']);
    Route::post('/billing-address', [\App\Http\Controllers\StripeController::class, 'updateBillingAddress']);
});

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
