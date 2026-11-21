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
        $raw = trim((string) ($amount ?? '0'));
        $raw = str_replace(['৳', ',', ' '], '', $raw);

        if ($raw === '' || ! is_numeric($raw)) {
            $raw = '0';
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '+-');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $precisionInput = explode('.', $raw, 2)[1] ?? '';
        if (isset($precisionInput[2]) && ord($precisionInput[2]) >= ord('5')) {
            $cents = (int) $fraction + 1;
            if ($cents === 100) {
                $whole = self::incrementWhole($whole);
                $fraction = '00';
            } else {
                $fraction = str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
            }
        }

        $formattedWhole = self::groupWhole($whole);
        $formatted = $formattedWhole.'.'.$fraction;

        if ($whole === '0' && $fraction === '00') {
            $negative = false;
        }

        if (! $negative) {
            return $withSymbol ? '৳'.$formatted : $formatted;
        }

        return $withSymbol ? '-৳'.$formatted : '-'.$formatted;
    }

    /**
     * Parse a formatted BDT string back to float.
     */
    public static function parse(string $value): float
    {
        return (float) str_replace(['৳', ','], '', $value);
    }

    private static function incrementWhole(string $whole): string
    {
        $digits = str_split(strrev($whole));
        $carry = 1;

        foreach ($digits as $index => $digit) {
            $sum = (int) $digit + $carry;
            $digits[$index] = (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        if ($carry === 1) {
            $digits[] = '1';
        }

        return strrev(implode('', $digits));
    }

    private static function groupWhole(string $whole): string
    {
        $firstGroupLength = strlen($whole) % 3 ?: 3;
        $groups = [substr($whole, 0, $firstGroupLength)];

        for ($index = $firstGroupLength; $index < strlen($whole); $index += 3) {
            $groups[] = substr($whole, $index, 3);
        }

        return implode(',', $groups);
    }
}
