<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;

class OrderService
{
    /**
     * Heuristic check — does the message text look like it contains an order?
     * Phase 2: replace with AI extraction.
     */
    public function looksLikeOrder(string $text): bool
    {
        $keywords = ['kg', 'gram', 'gm', 'pcs', 'piece', 'টুকু', 'কেজি', 'গ্রাম', 'লাগবে'];
        foreach ($keywords as $kw) {
            if (stripos($text, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create a pending draft order from a raw text message.
     * Items are stored as a single unparsed line for now.
     * Phase 2: parse with AI into structured line items.
     */
    public function createDraftFromText(string $text, Customer $customer, Conversation $conversation): Order
    {
        return Order::create([
            'order_number'    => Order::generateNumber(),
            'customer_id'     => $customer->id,
            'conversation_id' => $conversation->id,
            'status'          => 'pending',
            'items'           => [['name' => $text, 'qty' => 1, 'unit_price' => 0]],
            'subtotal'        => 0,
            'delivery_charge' => 60,
            'total'           => 0,
            'notes'           => 'Auto-created from chat — needs review',
        ]);
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $timestamps = match ($status) {
            'confirmed' => ['confirmed_at' => now()],
            'delivered' => ['delivered_at' => now()],
            default     => [],
        };

        $order->update(array_merge(['status' => $status], $timestamps));

        // Update customer totals when delivered
        if ($status === 'delivered') {
            $order->customer->increment('total_orders');
            $order->customer->increment('total_spent', $order->total);
        }

        return $order->fresh();
    }
}