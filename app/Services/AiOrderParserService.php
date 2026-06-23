<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Uses Claude to extract structured order line-items from free-text messages.
 *
 * Input:  "2 kg beef, 500 gram mutton, 1 kg chicken. Mirpur-10, 01711XXXXXX"
 * Output: [
 *   'items'   => [['name'=>'Beef','qty'=>2,'unit'=>'kg','unit_price'=>0],…],
 *   'address' => 'Mirpur-10',
 *   'phone'   => '01711XXXXXX',
 *   'raw_text'=> '…',
 * ]
 */
class AiOrderParserService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
    }

    public function parse(string $text): ?array
    {
        $prompt = <<<PROMPT
Extract order details from this customer message. Respond ONLY with valid JSON — no explanation, no markdown.

JSON schema:
{
  "items": [
    { "name": string, "qty": number, "unit": string, "unit_price": 0 }
  ],
  "address": string or null,
  "phone": string or null
}

Rules:
- "unit" should be "kg", "gm", "pcs", "ltr", or "item"
- Convert Bengali numerals (১২৩…) to Arabic
- If something is unclear, still include it as best guess with qty=1 unit="item"
- If no items found, return { "items": [], "address": null, "phone": null }

Customer message:
"{$text}"
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(12)->post($this->apiUrl, [
                'model'      => config('services.anthropic.model', 'claude-sonnet-4-6'),
                'max_tokens' => 400,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                Log::error('AiOrderParser API error', ['response' => $response->json()]);
                return null;
            }

            $raw  = $response->json('content.0.text', '');
            $json = $this->extractJson($raw);

            if (! $json || empty($json['items'])) {
                return null;
            }

            return array_merge($json, ['raw_text' => $text]);
        } catch (\Throwable $e) {
            Log::error('AiOrderParser exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractJson(string $raw): ?array
    {
        // Strip possible markdown fences
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', $raw);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }
}
