<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Due = 'due';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Due',
            self::Partial => 'Partial',
            self::Paid => 'Paid',
        };
    }

    /**
     * Derive the payment status from the amount paid against the grand total.
     */
    public static function fromAmount(float|int|string|null $paid, float|int|string|null $total): self
    {
        $paidAmount = round((float) ($paid ?? 0), 2);
        $totalAmount = round((float) ($total ?? 0), 2);

        if ($paidAmount <= 0) {
            return self::Due;
        }

        return $paidAmount >= $totalAmount ? self::Paid : self::Partial;
    }

    /**
     * All selectable values as value => label pairs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
