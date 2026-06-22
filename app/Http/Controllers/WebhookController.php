<?php

namespace App\Http\Controllers;

use App\Events\NewMessageReceived;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\AiReplyService;
use App\Services\Messaging\MessageNormalizer;
use App\Services\Messaging\MessengerService;
use App\Services\Messaging\WhatsAppService;
use App\Services\Messaging\TelegramService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private MessengerService  $messenger,
        private WhatsAppService   $whatsapp,
        private TelegramService   $telegram,
        private MessageNormalizer $normalizer,
        private AiReplyService    $ai,
        private OrderService      $orderService,
    ) {}

    // ── Messenger ─────────────────────────────────────────────────────────

    public function verifyMessenger(Request $request): Response
    {
        $challenge = $this->messenger->verifyWebhook($request->all());
        return $challenge !== false
            ? response($challenge, 200)
            : response('Verification failed', 403);
    }

    public function handleMessenger(Request $request): Response
    {
        $signature = $request->header('X-Hub-Signature-256', '');
        if (! $this->messenger->validateSignature($request->getContent(), $signature)) {
            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                if (! isset($event['message'])) continue;
                $normalized = $this->normalizer->fromMessenger($payload);
                $this->processIncoming($normalized, 'messenger');
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    // ── WhatsApp ──────────────────────────────────────────────────────────

    public function handleWhatsApp(Request $request): Response
    {
        if ($request->header('D360-API-KEY') !== config('services.whatsapp.api_key')) {
            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();
        $change  = $payload['entry'][0]['changes'][0]['value'] ?? [];

        if (isset($change['statuses'])) {
            return response('OK', 200); // skip delivery receipts
        }

        if (isset($change['messages'])) {
            $normalized = $this->normalizer->fromWhatsApp($payload);
            $this->processIncoming($normalized, 'whatsapp');
        }

        return response('OK', 200);
    }

    // ── Telegram ──────────────────────────────────────────────────────────

    public function handleTelegram(Request $request): Response
    {
        $payload    = $request->json()->all();
        $normalized = $this->normalizer->fromTelegram($payload);

        // Treat button callback as a text message
        if (isset($payload['callback_query'])) {
            $normalized['text']        = $payload['callback_query']['data'];
            $normalized['platform_id'] = (string) $payload['callback_query']['from']['id'];
        }

        $this->processIncoming($normalized, 'telegram');

        return response('OK', 200);
    }

    // ── Shared processing ─────────────────────────────────────────────────

    private function processIncoming(array $normalized, string $platform): void
    {
        try {
            // 1. Find / create customer
            $customer = Customer::findOrCreateByPlatform(
                $normalized['platform_id'],
                $platform,
                ['name' => $normalized['name']]
            );

            // 2. Find / create conversation
            $conversation = Conversation::firstOrCreate(
                ['customer_id' => $customer->id, 'platform' => $platform],
                ['status' => 'ai_handling']
            );

            // 3. Save inbound message
            $inbound = $conversation->addMessage($normalized['text'], 'inbound', 'customer');
            event(new NewMessageReceived($conversation->load('customer'), $inbound));

            // 4. Skip empty messages (image-only etc.)
            if (! trim($normalized['text'])) return;

            // 5. Typing indicator
            $this->sendTyping($platform, $normalized['platform_id']);

            // 6. AI reply
            $reply = $this->ai->generateReply($conversation, $normalized['text']);

            if ($reply) {
                $outbound = $conversation->addMessage($reply, 'outbound', 'ai');
                $this->sendReply($platform, $normalized['platform_id'], $reply);
                event(new NewMessageReceived($conversation, $outbound));
            } else {
                // AI handoff
                $conversation->update(['status' => 'human_handling']);
                $fallback = "একজন এজেন্ট শীঘ্রই আপনার সাথে কথা বলবেন। অনুগ্রহ করে অপেক্ষা করুন। 🙏";
                $outbound = $conversation->addMessage($fallback, 'outbound', 'ai');
                $this->sendReply($platform, $normalized['platform_id'], $fallback);
                event(new NewMessageReceived($conversation, $outbound));
            }

            // 7. Draft order if message looks like one
            if ($this->orderService->looksLikeOrder($normalized['text'])) {
                $this->orderService->createDraftFromText($normalized['text'], $customer, $conversation);
            }
        } catch (\Throwable $e) {
            Log::error("Webhook [{$platform}] failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendTyping(string $platform, string $recipientId): void
    {
        match ($platform) {
            'messenger' => $this->messenger->sendTypingOn($recipientId),
            'telegram'  => $this->telegram->sendTyping($recipientId),
            default     => null,
        };
    }

    private function sendReply(string $platform, string $recipientId, string $text): void
    {
        match ($platform) {
            'messenger' => $this->messenger->sendText($recipientId, $text),
            'whatsapp'  => $this->whatsapp->sendText($recipientId, $text),
            'telegram'  => $this->telegram->sendText($recipientId, $text),
        };
    }
}