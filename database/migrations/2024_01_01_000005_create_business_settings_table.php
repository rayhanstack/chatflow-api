<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, json, boolean
            $table->timestamps();
        });

        // Default values
        DB::table('business_settings')->insert([
            ['key' => 'business_name',    'value' => 'My Business',    'type' => 'string'],
            ['key' => 'business_type',    'value' => 'Online Shop',    'type' => 'string'],
            ['key' => 'phone_number',     'value' => '',               'type' => 'string'],
            ['key' => 'delivery_charge',  'value' => '60',             'type' => 'string'],
            ['key' => 'min_order',        'value' => '500',            'type' => 'string'],
            ['key' => 'working_hours',    'value' => 'Sat–Thu 9AM–10PM, Fri 2PM–10PM', 'type' => 'string'],
            ['key' => 'product_list',     'value' => '',               'type' => 'string'],
            ['key' => 'ai_enabled',       'value' => 'true',           'type' => 'boolean'],
            ['key' => 'messenger_enabled','value' => 'true',           'type' => 'boolean'],
            ['key' => 'whatsapp_enabled', 'value' => 'false',          'type' => 'boolean'],
            ['key' => 'telegram_enabled', 'value' => 'false',          'type' => 'boolean'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
