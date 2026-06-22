<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('platform', ['messenger', 'whatsapp', 'telegram'])->default('messenger');
            $table->enum('status', ['open', 'ai_handling', 'human_handling', 'resolved'])->default('open');
            $table->string('assigned_to')->nullable(); // admin user id
            $table->timestamp('last_message_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
