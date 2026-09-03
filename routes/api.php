<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DonorController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentProofController;
use App\Http\Controllers\Api\PaymentVerificationController;
use App\Http\Controllers\Api\PublicPaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\CheckSubscriptionLimitMiddleware;
use Illuminate\Support\Facades\Route;

// Public Auth, Webhook & Customer Payment Portal routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/webhooks/{provider}', [WebhookController::class, 'handle']);

// Public Customer Portal Routes
Route::get('/public/payments/{paymentNumber}', [PublicPaymentController::class, 'show']);
Route::post('/public/payments/{paymentNumber}/proof', [PublicPaymentController::class, 'uploadProof']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile & auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Donor / Customer Dashboard API
    Route::get('/donor/donations', [DonorController::class, 'index']);
    Route::get('/donor/stats', [DonorController::class, 'stats']);

    // Subscription & Billing API
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade']);
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);

    // Dashboard Analytics API
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Reports API
    Route::get('/reports/transactions', [ReportController::class, 'transactions']);

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
    Route::get('/payments/{id}/reconciliation', [PaymentController::class, 'reconciliation']);

    // Human Verification API (Quota-Enforced)
    Route::post('/payments/{id}/verify', [PaymentVerificationController::class, 'verify'])
        ->middleware(CheckSubscriptionLimitMiddleware::class);
});
