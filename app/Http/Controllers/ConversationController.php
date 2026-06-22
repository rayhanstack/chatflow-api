<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Messaging\MessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private MessengerService $messenger) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::with(['customer', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->platform, fn ($q) => $q->where('platform', $request->platform))
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return response()->json($conversations);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->markAsRead();

        return response()->json(
            $conversation->load(['customer', 'messages', 'orders'])
        );
    }

    /** Human agent sends a reply from the dashboard */
    public function reply(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate(['text' => 'required|string|max:2000']);

        // Save to DB
        $message = $conversation->addMessage($validated['text'], 'outbound', 'human');

        // Send to platform
        if ($conversation->platform === 'messenger') {
            $this->messenger->sendText(
                $conversation->customer->platform_id,
                $validated['text']
            );
        }
        // Phase 2: add WhatsApp / Telegram send here

        return response()->json($message, 201);
    }

    /** Toggle between AI and human handling */
    public function updateStatus(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,ai_handling,human_handling,resolved',
        ]);

        $conversation->update($validated);

        return response()->json($conversation);
    }
}