<?php

namespace App\Services;

/**
 * Phase 1: keyword-based auto-reply engine.
 * Phase 2: swap detectIntent() for Claude/OpenAI API call.
 */
class AutoReplyService
{
    // Map keyword patterns to intent handlers
    private array $intentMap = [
        'greeting'    => ['/^(hi|hello|hey|assalamu|salam|কেমন|হ্যালো|হাই)/iu'],
        'price_list'  => ['/(price|দাম|কত|rate|মূল্য)/iu'],
        'order'       => ['/(order|অর্ডার|নিতে চাই|লাগবে|কিনতে)/iu'],
        'status'      => ['/(status|কোথায়|কখন|delivery|ডেলিভারি)/iu'],
        'location'    => ['/(address|location|কোথায় পাবো|ঠিকানা)/iu'],
        'hours'       => ['/(open|close|time|সময়|কখন খোলা)/iu'],
    ];

    public function detectIntent(string $text): string
    {
        $text = trim($text);

        foreach ($this->intentMap as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    return $intent;
                }
            }
        }

        return 'unknown';
    }

    public function getReply(string $intent, array $context = []): ?string
    {
        return match ($intent) {
            'greeting' => $this->greetingReply(),
            'price_list' => $this->priceListReply(),
            'order'    => $this->orderReply(),
            'status'   => $this->statusReply($context['order_number'] ?? null),
            'location' => $this->locationReply(),
            'hours'    => $this->hoursReply(),
            default    => null, // null = hand off to human
        };
    }

    // ── Reply templates (client customises these) ──────────────────────────

    private function greetingReply(): string
    {
        return "Assalamu Alaikum! 👋 ChatFlow BD-তে স্বাগতম।\n\n"
            . "আপনি কী জানতে চান?\n"
            . "1️⃣ Price list\n"
            . "2️⃣ Order করতে চাই\n"
            . "3️⃣ Order status\n\n"
            . "যেকোনো নম্বর লিখুন অথবা সরাসরি প্রশ্ন করুন।";
    }

    private function priceListReply(): string
    {
        // Phase 2: fetch from products table dynamically
        return "📋 আমাদের Price List:\n\n"
            . "🥩 Beef (গরু) — ৳৭৫০/kg\n"
            . "🐑 Mutton (খাসি) — ৳১,১০০/kg\n"
            . "🐔 Chicken (মুরগি) — ৳২২০/kg\n\n"
            . "Minimum order: ৳৫০০\nDelivery charge: ৳৬০ (Dhaka)\n\n"
            . "Order করতে চাইলে পরিমাণ লিখুন 👇";
    }

    private function orderReply(): string
    {
        return "✅ অর্ডার নিতে আমরা প্রস্তুত!\n\n"
            . "নিচের তথ্য দিন:\n"
            . "1. কী কী লাগবে এবং কতটুকু\n"
            . "2. আপনার ঠিকানা\n"
            . "3. মোবাইল নম্বর\n\n"
            . "উদাহরণ: \"1kg beef, 500g chicken, Mirpur-10, 01711XXXXXX\"";
    }

    private function statusReply(?string $orderNumber): string
    {
        if ($orderNumber) {
            return "আপনার অর্ডার #{$orderNumber} এর status আমরা চেক করছি, একটু অপেক্ষা করুন। 🔍";
        }

        return "Order status জানতে আপনার Order Number দিন (যেমন: ORD-1045)।";
    }

    private function locationReply(): string
    {
        return "📍 আমাদের ঠিকানা:\nXXX Road, Dhaka-1200\n\nGoogle Maps: https://maps.google.com/?q=...";
    }

    private function hoursReply(): string
    {
        return "🕐 আমাদের সময়সূচি:\nশনি–বৃহস্পতি: সকাল ৯টা – রাত ১০টা\nশুক্রবার: দুপুর ২টা – রাত ১০টা";
    }
}
