<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesInvoiceStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::Voided => 'Voided',
        };
    }

    /**
     * Whether the invoice may still be edited or deleted.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
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
