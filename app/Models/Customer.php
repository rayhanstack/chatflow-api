<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'platform_id',
        'platform',
        'profile_pic',
        'total_orders',
        'total_spent',
        'metadata',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'total_spent' => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public static function findOrCreateByPlatform(string $platformId, string $platform, array $data = []): self
    {
        return self::firstOrCreate(
            ['platform_id' => $platformId, 'platform' => $platform],
            array_merge(['name' => 'Unknown'], $data)
        );
    }
}
