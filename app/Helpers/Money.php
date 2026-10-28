<?php

declare(strict_types=1);

namespace App\Helpers;

class Money
{
    /**
     * Format an amount as Bangladeshi Taka (BDT).
     * e.g. 12345.50 → ৳12,345.50
     */
    public static function format(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $value = round((float) ($amount ?? 0), 2);
        $formatted = number_format($value, 2);

        return $withSymbol ? '৳' . $formatted : $formatted;
    }

    /**
     * Parse a formatted BDT string back to float.
     */
    public static function parse(string $value): float
    {
        return (float) str_replace(['৳', ','], '', $value);
    }
}
