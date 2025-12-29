<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth Group
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
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

