<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('platform_id')->unique(); // messenger PSID, wa number, tg id
            $table->enum('platform', ['messenger', 'whatsapp', 'telegram'])->default('messenger');
            $table->string('profile_pic')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->json('metadata')->nullable(); // extra platform-specific data
            $table->timestamps();

            $table->index(['platform', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
