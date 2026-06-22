<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public Message $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('conversations'),           // all conversations (for sidebar unread badge)
            new Channel("conversation.{$this->conversation->id}"), // specific chat window
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.message';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'customer'        => [
                'id'   => $this->conversation->customer->id,
                'name' => $this->conversation->customer->name,
            ],
            'message' => [
                'id'          => $this->message->id,
                'content'     => $this->message->content,
                'direction'   => $this->message->direction,
                'sender_type' => $this->message->sender_type,
                'created_at'  => $this->message->created_at->toISOString(),
            ],
        ];
    }
}