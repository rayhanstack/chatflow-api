<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'conversation_id',
        'status',
        'items',
        'subtotal',
        'delivery_charge',
        'total',
        'delivery_address',
        'delivery_area',
        'notes',
        'confirmed_at',
        'delivered_at',
    ];

    protected $casts = [
        'items'           => 'array',
        'subtotal'        => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'total'           => 'decimal:2',
        'confirmed_at'    => 'datetime',
        'delivered_at'    => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public static function generateNumber(): string
    {
        $last = self::latest()->first();
        $next = $last ? ((int) substr($last->order_number, 4)) + 1 : 1001;
        return 'ORD-' . $next;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'          => 'Pending',
            'confirmed'        => 'Confirmed',
            'processing'       => 'Processing',
            'out_for_delivery' => 'Out for delivery',
            'delivered'        => 'Delivered',
            'cancelled'        => 'Cancelled',
            default            => $this->status,
        };
    }
}