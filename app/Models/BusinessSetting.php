<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key-value store for business configuration.
 * Used by AiReplyService to build dynamic system prompts.
 */
class BusinessSetting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    protected $casts = ['value' => 'string'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();
        return $setting?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function allAsArray(): array
    {
        return self::all()->pluck('value', 'key')->toArray();
    }
}
