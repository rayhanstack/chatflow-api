<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// ── Auth ───────────────────────────────────────────────────────────────────
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});

// ── Webhooks (public) ──────────────────────────────────────────────────────
Route::prefix('webhook')->group(function () {
    Route::get('messenger', [WebhookController::class, 'verifyMessenger']);
    Route::post('messenger', [WebhookController::class, 'handleMessenger']);
    Route::post('whatsapp', [WebhookController::class, 'handleWhatsApp']);
    Route::post('telegram', [WebhookController::class, 'handleTelegram']);
});

// ── Protected dashboard API ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Conversations
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('conversations/{conversation}/reply', [ConversationController::class, 'reply']);
    Route::patch('conversations/{conversation}/status', [ConversationController::class, 'updateStatus']);

    // Orders
    Route::get('orders/stats', [OrderController::class, 'stats']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);

    // Invoice (Phase 3)
    Route::get('orders/{order}/invoice', [InvoiceController::class, 'download']);

    // Customers (Phase 3)
    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);

    // Analytics (Phase 3)
    Route::prefix('analytics')->group(function () {
        Route::get('overview', [AnalyticsController::class, 'overview']);
        Route::get('orders-per-day', [AnalyticsController::class, 'ordersPerDay']);
        Route::get('platform-breakdown', [AnalyticsController::class, 'platformBreakdown']);
        Route::get('top-products', [AnalyticsController::class, 'topProducts']);
        Route::get('order-status-dist', [AnalyticsController::class, 'orderStatusDist']);
    });

    // Settings
    Route::get('settings', [SettingsController::class, 'index']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::post('settings/telegram/register-webhook', [SettingsController::class, 'registerTelegramWebhook']);
});