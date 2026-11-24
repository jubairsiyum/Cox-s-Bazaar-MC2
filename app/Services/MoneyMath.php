<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * High-precision monetary arithmetic service.
 *
 * Enforces the non-negotiable architectural rule: "Money is never a float."
 * All calculations are executed using bcmath to completely eliminate binary
 * floating-point rounding errors and 1-paisa drift in ledger entries.
 */
final class MoneyMath
{
    public const DEFAULT_SCALE = 2;

    /**
     * Normalize an input into a clean numeric string for bcmath.
     */
    public static function clean(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // Convert float safely without scientific notation
            return number_format($value, 4, '.', '');
        }

        $trimmed = trim(str_replace(['৳', ',', ' '], '', $value));

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return '0.00';
        }

        return $trimmed;
    }

    /**
     * Add two amounts: a + b
     */
    public static function add(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcadd(self::clean($a), self::clean($b), $scale);
    }

    /**
     * Subtract two amounts: a - b
     */
    public static function sub(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcsub(self::clean($a), self::clean($b), $scale);
    }

    /**
     * Multiply two amounts: a * b
     */
    public static function mul(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcmul(self::clean($a), self::clean($b), $scale);
    }

    /**
     * Divide: a / b
     *
     * @throws InvalidArgumentException When dividing by zero.
     */
    public static function div(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): string
    {
        $divisor = self::clean($b);
        if (bccomp($divisor, '0', $scale) === 0) {
            throw new InvalidArgumentException('Division by zero in monetary arithmetic.');
        }

        return bcdiv(self::clean($a), $divisor, $scale);
    }

    /**
     * Round an amount using standard half-up rounding to a given scale.
     */
    public static function round(string|int|float|null $amount, int $scale = self::DEFAULT_SCALE): string
    {
        $amt = self::clean($amount);
        $negative = str_starts_with($amt, '-');
        $unsigned = ltrim($amt, '-');

        if (! str_contains($unsigned, '.')) {
            return ($negative ? '-' : '').$unsigned.'.'.str_repeat('0', $scale);
        }

        [$whole, $fraction] = explode('.', $unsigned, 2);

        if (strlen($fraction) <= $scale) {
            return ($negative ? '-' : '').$whole.'.'.str_pad($fraction, $scale, '0');
        }

        $nextDigit = (int) $fraction[$scale];
        $truncated = $whole.'.'.substr($fraction, 0, $scale);

        if ($nextDigit >= 5) {
            $increment = '0.'.str_repeat('0', max(0, $scale - 1)).'1';
            $rounded = bcadd($truncated, $increment, $scale);
        } else {
            $rounded = $truncated;
        }

        return ($negative ? '-' : '').$rounded;
    }

    /**
     * Calculate percentage: (amount * rate) / 100 with half-up rounding.
     */
    public static function percent(string|int|float|null $amount, string|int|float|null $rate, int $scale = self::DEFAULT_SCALE): string
    {
        $amt = self::clean($amount);
        $rt = self::clean($rate);

        // Compute with extra precision before rounding
        $multiplied = bcmul($amt, $rt, $scale + 4);
        $raw = bcdiv($multiplied, '100', $scale + 4);

        return self::round($raw, $scale);
    }

    /**
     * Compare two amounts: returns -1 if a < b, 0 if a == b, 1 if a > b.
     */
    public static function comp(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): int
    {
        return bccomp(self::clean($a), self::clean($b), $scale);
    }

    /**
     * Returns true if a > b.
     */
    public static function gt(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) === 1;
    }

    /**
     * Returns true if a >= b.
     */
    public static function gte(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) >= 0;
    }

    /**
     * Returns true if a < b.
     */
    public static function lt(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) === -1;
    }

    /**
     * Returns true if a <= b.
     */
    public static function lte(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) <= 0;
    }

    /**
     * Returns true if a == b.
     */
    public static function eq(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) === 0;
    }

    /**
     * Return the larger of two amounts.
     */
    public static function max(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): string
    {
        return self::gte($a, $b, $scale) ? self::clean($a) : self::clean($b);
    }

    /**
     * Return the smaller of two amounts.
     */
    public static function min(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): string
    {
        return self::lte($a, $b, $scale) ? self::clean($a) : self::clean($b);
    }
}
