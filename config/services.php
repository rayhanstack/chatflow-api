<?php

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // ── Messenger ─────────────────────────────────────────────────────────
    'messenger' => [
        'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
        'app_secret'        => env('MESSENGER_APP_SECRET'),
        'verify_token'      => env('MESSENGER_VERIFY_TOKEN'),
    ],

    // ── WhatsApp (Phase 2) ────────────────────────────────────────────────
    'whatsapp' => [
        'api_url'      => env('WHATSAPP_API_URL', 'https://waba.360dialog.io/v1'),
        'api_key'      => env('WHATSAPP_API_KEY'),
        'phone_number' => env('WHATSAPP_PHONE_NUMBER'),
    ],

    // ── Telegram (Phase 2) ────────────────────────────────────────────────
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

    // ── AI (Phase 2) ──────────────────────────────────────────────────────
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

];
