<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2 AI reply engine using Claude (Anthropic).
 * Drop-in replacement for AutoReplyService.
 * Swap in WebhookController by binding in AppServiceProvider.
 */
class AiReplyService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    /**
     * Generate a reply for the given conversation using recent history as context.
     * Returns null if AI cannot answer (triggers human handoff).
     */
    public function generateReply(Conversation $conversation, string $latestMessage): ?string
    {
        $businessContext = $this->buildBusinessContext();
        $history         = $this->buildMessageHistory($conversation);

        $systemPrompt = <<<PROMPT
{$businessContext}

You are a helpful customer service assistant for this business. Respond in the same language the customer uses (Bengali or English). Keep replies concise and friendly.

Rules:
- If the customer wants to place an order, confirm the items and ask for their delivery address and phone number.
- If you cannot confidently answer, reply ONLY with the exact text: [HANDOFF]
- Never make up prices or policies not mentioned above.
- For complex complaints or angry customers, reply with [HANDOFF].
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(15)->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => 500,
                'system'     => $systemPrompt,
                'messages'   => array_merge($history, [
                    ['role' => 'user', 'content' => $latestMessage],
                ]),
            ]);

            if ($response->failed()) {
                Log::error('Claude API error', ['response' => $response->json()]);
                return null;
            }

            $text = $response->json('content.0.text', '');

            if (str_contains($text, '[HANDOFF]')) {
                return null; // signal to WebhookController to hand off
            }

            return trim($text);
        } catch (\Throwable $e) {
            Log::error('AI reply exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Build last 10 messages as Claude conversation history.
     */
    private function buildMessageHistory(Conversation $conversation): array
    {
        return $conversation
            ->messages()
            ->latest()
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn (Message $msg) => [
                'role'    => $msg->direction === 'inbound' ? 'user' : 'assistant',
                'content' => $msg->content,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Business context loaded from DB settings (Phase 3) or hardcoded for now.
     * In Phase 3: replace with BusinessSetting::getContext()
     */
    private function buildBusinessContext(): string
    {
        $s = \App\Models\BusinessSetting::allAsArray();

        return <<<CTX
Business: {$s['business_name']}
Type: {$s['business_type']}

Products/Services:
{$s['product_list']}

Policies:
- Delivery charge: ৳{$s['delivery_charge']} within Dhaka
- Minimum order: ৳{$s['min_order']}
- Delivery time: Same day if ordered before 2 PM
- Payment: Cash on delivery / bKash / Nagad

Working hours: {$s['working_hours']}
Contact: {$s['phone_number']}
CTX;
    }

    /**
     * Backward-compatible shim so existing WebhookController works unchanged
     * when you swap AutoReplyService → AiReplyService in AppServiceProvider.
     */
    public function detectIntent(string $text): string
    {
        return 'ai'; // AI handles everything — no keyword matching needed
    }

    public function getReply(string $intent, array $context = []): ?string
    {
        // Not used directly — generateReply() is called from WebhookController Phase 2 path.
        return null;
    }
}
