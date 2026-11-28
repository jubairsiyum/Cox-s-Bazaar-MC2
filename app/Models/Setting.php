<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DashboardMetricsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    private const CACHE_TTL_SECONDS = 43200;

    private const CACHE_NULL_SENTINEL = '__setting_null__';

    /** @var array<string, mixed> */
    private static array $memo = [];

    /** @var array<string, array<string, mixed>> */
    private static array $groupMemo = [];

    protected $fillable = ['key', 'value', 'group', 'label', 'updated_by'];

    protected $casts = ['updated_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saved(function (Setting $setting): void {
            self::forgetCache((string) ($setting->getAttributes()['key'] ?? ''), $setting->getAttributes()['group'] ?? null);
        });

        static::deleted(function (Setting $setting): void {
            self::forgetCache((string) ($setting->getAttributes()['key'] ?? ''), $setting->getAttributes()['group'] ?? null);
        });
    }

    // ── Static helpers ────────────────────────────────────

    /**
     * Get a setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            $cached = self::$memo[$key];
        } else {
            $cached = Cache::remember(
                self::cacheKey($key),
                self::CACHE_TTL_SECONDS,
                static fn (): mixed => static::query()->where('key', $key)->value('value') ?? self::CACHE_NULL_SENTINEL
            );
            self::$memo[$key] = $cached;
        }

        return $cached === self::CACHE_NULL_SENTINEL ? $default : $cached;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $updatedBy]
        );

        self::forgetCache($key, $setting->getAttributes()['group'] ?? null);
    }

    /**
     * Get all settings in a group as key→value array.
     *
     * @return array<string, mixed>
     */
    public static function group(string $group): array
    {
        if (array_key_exists($group, self::$groupMemo)) {
            return self::$groupMemo[$group];
        }

        return self::$groupMemo[$group] = Cache::remember(
            self::groupCacheKey($group),
            self::CACHE_TTL_SECONDS,
            static fn (): array => static::query()
                ->where('group', $group)
                ->pluck('value', 'key')
                ->toArray()
        );
    }

    /**
     * Invalidate cached values for a setting key and optional group.
     */
    public static function forgetCache(string $key, ?string $group = null): void
    {
        unset(self::$memo[$key]);
        Cache::forget(self::cacheKey($key));

        if ($group !== null && $group !== '') {
            unset(self::$groupMemo[$group]);
            Cache::forget(self::groupCacheKey($group));
        } else {
            self::$groupMemo = [];
        }

        if (! str_starts_with($key, 'numbering_counter_') && class_exists(DashboardMetricsService::class)) {
            DashboardMetricsService::flushCache();
        }
    }

    private static function cacheKey(string $key): string
    {
        return 'setting.value.'.$key;
    }

    private static function groupCacheKey(string $group): string
    {
        return 'setting.group.'.$group;
    }

    // ── Relationships ──────────────────────────────────────

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
