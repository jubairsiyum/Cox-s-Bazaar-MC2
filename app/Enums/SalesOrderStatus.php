<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
            self::Invoiced => 'Invoiced',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the order may still be edited or deleted.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Confirmed;
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
