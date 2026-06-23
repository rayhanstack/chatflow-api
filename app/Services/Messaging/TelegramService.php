<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $apiBase;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->apiBase  = "https://api.telegram.org/bot{$this->botToken}";
    }

    // ── Register webhook with Telegram ────────────────────────────────────

    public function registerWebhook(string $url): bool
    {
        $response = Http::post("{$this->apiBase}/setWebhook", [
            'url'             => $url,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => true,
        ]);
        return $response->successful();
    }

    // ── Send plain text ────────────────────────────────────────────────────

    public function sendText(string|int $chatId, string $text): bool
    {
        return $this->call('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    // ── Send inline keyboard buttons ──────────────────────────────────────

    public function sendButtons(string|int $chatId, string $text, array $options): bool
    {
        $keyboard = array_map(
            fn ($opt) => [['text' => $opt, 'callback_data' => strtoupper(str_replace(' ', '_', $opt))]],
            $options
        );

        return $this->call('sendMessage', [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
    }

    // ── Send typing indicator ─────────────────────────────────────────────

    public function sendTyping(string|int $chatId): void
    {
        $this->call('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
    }

    // ── Private helper ────────────────────────────────────────────────────

    private function call(string $method, array $params): bool
    {
        $response = Http::post("{$this->apiBase}/{$method}", $params);

        if ($response->failed()) {
            Log::error("Telegram {$method} failed", ['error' => $response->json()]);
            return false;
        }

        return true;
    }
}
