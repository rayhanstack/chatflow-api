<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Services\Messaging\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private TelegramService $telegram) {}

    public function index(): JsonResponse
    {
        return response()->json(BusinessSetting::allAsArray());
    }

    public function update(Request $request): JsonResponse
    {
        $allowed = [
            'business_name', 'business_type', 'phone_number',
            'delivery_charge', 'min_order', 'working_hours', 'product_list',
            'ai_enabled', 'messenger_enabled', 'whatsapp_enabled', 'telegram_enabled',
        ];

        foreach ($request->only($allowed) as $key => $value) {
            BusinessSetting::set($key, $value);
        }

        return response()->json(['message' => 'Settings saved']);
    }

    /**
     * One-click Telegram webhook registration.
     * Call this after saving the bot token in .env.
     */
    public function registerTelegramWebhook(Request $request): JsonResponse
    {
        $webhookUrl = url('/api/webhook/telegram');
        $success    = $this->telegram->registerWebhook($webhookUrl);

        return response()->json([
            'success' => $success,
            'webhook' => $webhookUrl,
        ]);
    }
}
