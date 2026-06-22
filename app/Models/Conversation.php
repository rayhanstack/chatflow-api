<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'customer_id',
        'platform',
        'status',
        'assigned_to',
        'last_message_at',
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
        $this->messages()->update(['is_read' => true]);
    }

    public function addMessage(string $content, string $direction, string $senderType, array $meta = []): Message
    {
        $message = $this->messages()->create([
            'direction'   => $direction,
            'sender_type' => $senderType,
            'type'        => 'text',
            'content'     => $content,
            'metadata'    => $meta ?: null,
        ]);

        $this->update([
            'last_message_at' => now(),
            'unread_count'    => $direction === 'inbound' ? $this->unread_count + 1 : $this->unread_count,
        ]);

        return $message;
    }
}
