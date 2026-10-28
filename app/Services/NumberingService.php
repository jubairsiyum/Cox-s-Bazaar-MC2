<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class NumberingService
{
    /**
     * Generate the next sequential document number.
     *
     * Uses a database-level lock to ensure uniqueness under concurrency.
     * Format: {PREFIX}-{YYYYMM}-{SEQUENCE} e.g. INV-202610-000001
     *
     * @param  string $settingKey  Settings key for the prefix, e.g. 'invoice_prefix'
     * @param  string $default     Default prefix if not configured
     * @param  int    $padLength   Zero-padding length for the sequence
     */
    public static function next(
        string $settingKey,
        string $default   = 'DOC',
        int    $padLength = 6,
    ): string {
        $prefix  = Setting::get($settingKey, $default);
        $yearMonth = now()->format('Ym');
        $counterKey = "numbering_counter_{$settingKey}_{$yearMonth}";

        // Atomic increment: read-modify-write under a named lock
        $counter = (int) Setting::lockForUpdate()
            ->where('key', $counterKey)
            ->value('value');

        $counter++;

        Setting::updateOrCreate(
            ['key'   => $counterKey],
            ['value' => $counter, 'group' => 'numbering']
        );

        return "{$prefix}-{$yearMonth}-" . str_pad((string) $counter, $padLength, '0', STR_PAD_LEFT);
    }
}
