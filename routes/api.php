<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentProofController;
use Illuminate\Support\Facades\Route;

// Public Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile & auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Business tenant management
    Route::prefix('business')->group(function () {
        Route::get('/', [BusinessController::class, 'show']);
        Route::put('/', [BusinessController::class, 'update']);
        Route::get('/users', [BusinessController::class, 'users']);
        Route::post('/users', [BusinessController::class, 'addStaff']);
    });

    // Invoices API
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);

    // Payments API
    Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);

    // Payment Proof API
    Route::post('/payments/{id}/proof', [PaymentProofController::class, 'upload']);
    Route::get('/payments/{id}/proof', [PaymentProofController::class, 'show']);

    // Payment Validation & Risk Analysis API
    Route::get('/payments/{id}/analysis', [PaymentController::class, 'analysis']);
});
