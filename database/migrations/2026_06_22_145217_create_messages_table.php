<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('sender_type', ['customer', 'ai', 'human']);
            $table->enum('type', ['text', 'image', 'audio', 'file', 'order_card'])->default('text');
            $table->text('content');
            $table->string('platform_message_id')->nullable(); // original message id from platform
            $table->boolean('is_read')->default(false);
            $table->json('metadata')->nullable(); // attachments, quick replies, etc.
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
