<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a supplier payment request: open while the targeted branch
 * still owes an answer, actioned once a payment was recorded against it and
 * cancelled when the request was withdrawn.
 */
enum SupplierPaymentRequestStatus: string
{
    case Open = 'open';
    case Actioned = 'actioned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Actioned => 'Actioned',
            self::Cancelled => 'Cancelled',
        };
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
