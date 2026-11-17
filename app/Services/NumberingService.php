<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    /**
     * Generate the next sequential document number.
     *
     * Uses a database-level lock to ensure uniqueness under concurrency.
     * Format: {PREFIX}-{YYYYMM}-{SEQUENCE} e.g. INV-202610-000001
     *
     * @param  string  $settingKey  Settings key for the prefix, e.g. 'invoice_prefix'
     * @param  string  $default  Default prefix if not configured
     * @param  int  $padLength  Zero-padding length for the sequence
     */
    public static function next(
        string $settingKey,
        string $default = 'DOC',
        int $padLength = 6,
    ): string {
        return DB::transaction(function () use ($settingKey, $default, $padLength) {
            $prefix = Setting::get($settingKey, $default);
            $yearMonth = now()->format('Ym');
            $counterKey = "numbering_counter_{$settingKey}_{$yearMonth}";

            $counter = (int) Setting::lockForUpdate()
                ->where('key', $counterKey)
                ->value('value');

            $counter++;

            Setting::updateOrCreate(
                ['key' => $counterKey],
                ['value' => $counter, 'group' => 'numbering']
            );

            return "{$prefix}-{$yearMonth}-".str_pad((string) $counter, $padLength, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Generate the next simple sequential code without dates.
     * Format: {PREFIX}{SEQUENCE} e.g. SP0001
     *
     * @param  string  $settingKey  Settings key for the prefix, e.g. 'supplier_prefix'
     * @param  string  $default  Default prefix if not configured (e.g. 'SP')
     * @param  int  $padLength  Zero-padding length for the sequence
     */
    public static function nextSequential(
        string $settingKey,
        string $default = 'SP',
        int $padLength = 4,
    ): string {
        return DB::transaction(function () use ($settingKey, $default, $padLength) {
            $prefix = Setting::get($settingKey, $default);
            $counterKey = "numbering_counter_{$settingKey}";

            $counter = (int) Setting::lockForUpdate()
                ->where('key', $counterKey)
                ->value('value');

            $counter++;

            Setting::updateOrCreate(
                ['key' => $counterKey],
                ['value' => $counter, 'group' => 'numbering']
            );

            return "{$prefix}".str_pad((string) $counter, $padLength, '0', STR_PAD_LEFT);
        });
    }
}
