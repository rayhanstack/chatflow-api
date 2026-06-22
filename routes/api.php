<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WebhookController;
// use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// ── Auth ───────────────────────────────────────────────────────────────────
Route::post('auth/login',  [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);
});

// ── Webhooks (public — platform callbacks) ─────────────────────────────────
Route::prefix('webhook')->group(function () {
    // Messenger
    Route::get('messenger',   [WebhookController::class, 'verifyMessenger']);
    Route::post('messenger',  [WebhookController::class, 'handleMessenger']);

    // WhatsApp (Phase 2)
    Route::post('whatsapp',   [WebhookController::class, 'handleWhatsApp']);

    // Telegram (Phase 2)
    Route::post('telegram',   [WebhookController::class, 'handleTelegram']);
});

// ── Dashboard API (Sanctum protected) ─────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Conversations
    Route::get('conversations',                         [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}',          [ConversationController::class, 'show']);
    Route::post('conversations/{conversation}/reply',   [ConversationController::class, 'reply']);
    Route::patch('conversations/{conversation}/status', [ConversationController::class, 'updateStatus']);

    // Orders
    Route::get('orders/stats',     [OrderController::class, 'stats']);
    Route::get('orders',           [OrderController::class, 'index']);
    Route::get('orders/{order}',   [OrderController::class, 'show']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);

    // Settings (Phase 2)
    // Route::get('settings',         [SettingsController::class, 'index']);
    // Route::put('settings',         [SettingsController::class, 'update']);
    // Route::post('settings/telegram/register-webhook', [SettingsController::class, 'registerTelegramWebhook']);
});