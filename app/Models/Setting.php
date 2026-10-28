<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'label', 'updated_by'];

    protected $casts = ['updated_at' => 'datetime'];

    // ── Static helpers ────────────────────────────────────

    /**
     * Get a setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        static::updateOrCreate(
            ['key'  => $key],
            ['value' => $value, 'updated_by' => $updatedBy]
        );
    }

    /**
     * Get all settings in a group as key→value array.
     */
    public static function group(string $group): array
    {
        return static::where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }

    // ── Relationships ──────────────────────────────────────

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
