<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp via 360dialog (Meta's recommended BSP for Bangladesh).
 * Alternative: use Meta Cloud API directly with the same method signatures.
 */
class WhatsAppService
{
    private string $apiUrl;
    private string $apiKey;
    private string $phoneNumber;

    public function __construct()
    {
        $this->apiUrl      = config('services.whatsapp.api_url');
        $this->apiKey      = config('services.whatsapp.api_key');
        $this->phoneNumber = config('services.whatsapp.phone_number');
    }

    // ── Webhook verification ───────────────────────────────────────────────

    public function verifyWebhook(array $params): string|false
    {
        // 360dialog uses a simple token check in headers — handled in controller
        return $params['hub_challenge'] ?? false;
    }

    // ── Send plain text ────────────────────────────────────────────────────

    public function sendText(string $to, string $text): bool
    {
        return $this->send($to, [
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    // ── Send interactive buttons (WhatsApp native quick replies) ──────────

    public function sendButtons(string $to, string $body, array $options): bool
    {
        $buttons = array_slice(
            array_map(fn ($opt, $i) => [
                'type'  => 'reply',
                'reply' => ['id' => "btn_{$i}", 'title' => mb_substr($opt, 0, 20)],
            ], $options, array_keys($options)),
            0, 3 // WhatsApp max 3 buttons
        );

        return $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => $buttons],
            ],
        ]);
    }

    // ── Send a list message ────────────────────────────────────────────────

    public function sendList(string $to, string $body, string $buttonLabel, array $items): bool
    {
        $rows = array_map(fn ($item, $i) => [
            'id'          => "item_{$i}",
            'title'       => mb_substr($item['title'], 0, 24),
            'description' => mb_substr($item['description'] ?? '', 0, 72),
        ], $items, array_keys($items));

        return $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $body],
                'action' => [
                    'button'   => $buttonLabel,
                    'sections' => [['rows' => $rows]],
                ],
            ],
        ]);
    }

    // ── Mark message as read ───────────────────────────────────────────────

    public function markRead(string $messageId): void
    {
        Http::withHeaders(['D360-API-KEY' => $this->apiKey])
            ->post("{$this->apiUrl}/messages", [
                'status'     => 'read',
                'message_id' => $messageId,
            ]);
    }

    // ── Private send ──────────────────────────────────────────────────────

    private function send(string $to, array $message): bool
    {
        $response = Http::withHeaders(['D360-API-KEY' => $this->apiKey])
            ->post("{$this->apiUrl}/messages", array_merge(
                ['to' => $to, 'type' => $message['type']],
                $message
            ));

        if ($response->failed()) {
            Log::error('WhatsApp send failed', [
                'to'    => $to,
                'error' => $response->json(),
            ]);
            return false;
        }

        return true;
    }
}
