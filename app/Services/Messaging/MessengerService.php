<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerService
{
    private string $pageAccessToken;
    private string $appSecret;
    private string $verifyToken;
    private string $apiVersion = 'v19.0';

    public function __construct()
    {
        $this->pageAccessToken = config('services.messenger.page_access_token');
        $this->appSecret       = config('services.messenger.app_secret');
        $this->verifyToken     = config('services.messenger.verify_token');
    }

    // ── Webhook verification (GET) ─────────────────────────────────────────

    public function verifyWebhook(array $params): string|false
    {
        if (
            ($params['hub_mode'] ?? '') === 'subscribe' &&
            ($params['hub_verify_token'] ?? '') === $this->verifyToken
        ) {
            return $params['hub_challenge'];
        }

        return false;
    }

    // ── Send a plain text message ──────────────────────────────────────────

    public function sendText(string $recipientId, string $text): bool
    {
        return $this->sendMessage($recipientId, ['text' => $text]);
    }

    // ── Send quick reply buttons ───────────────────────────────────────────

    public function sendQuickReplies(string $recipientId, string $text, array $options): bool
    {
        $quickReplies = array_map(fn ($opt) => [
            'content_type' => 'text',
            'title'        => $opt,
            'payload'      => strtoupper(str_replace(' ', '_', $opt)),
        ], $options);

        return $this->sendMessage($recipientId, [
            'text'          => $text,
            'quick_replies' => $quickReplies,
        ]);
    }

    // ── Fetch sender profile (name, profile pic) ───────────────────────────

    public function getSenderProfile(string $senderId): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/{$senderId}", [
            'fields'       => 'name,profile_pic',
            'access_token' => $this->pageAccessToken,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::warning('Messenger: could not fetch profile', ['sender_id' => $senderId]);
        return [];
    }

    // ── Send typing indicator ──────────────────────────────────────────────

    public function sendTypingOn(string $recipientId): void
    {
        $this->sendAction($recipientId, 'typing_on');
    }

    public function sendTypingOff(string $recipientId): void
    {
        $this->sendAction($recipientId, 'typing_off');
    }

    // ── Validate webhook signature ─────────────────────────────────────────

    public function validateSignature(string $rawBody, string $signature): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->appSecret);
        return hash_equals($expected, $signature);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function sendMessage(string $recipientId, array $message): bool
    {
        $response = Http::withToken($this->pageAccessToken)
            ->post("https://graph.facebook.com/{$this->apiVersion}/me/messages", [
                'recipient' => ['id' => $recipientId],
                'message'   => $message,
            ]);

        if ($response->failed()) {
            Log::error('Messenger send failed', [
                'recipient' => $recipientId,
                'error'     => $response->json(),
            ]);
            return false;
        }

        return true;
    }

    private function sendAction(string $recipientId, string $action): void
    {
        Http::withToken($this->pageAccessToken)
            ->post("https://graph.facebook.com/{$this->apiVersion}/me/messages", [
                'recipient'     => ['id' => $recipientId],
                'sender_action' => $action,
            ]);
    }
}
