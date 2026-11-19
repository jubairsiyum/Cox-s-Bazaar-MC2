<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How money physically moved for an account transaction or transfer.
 */
enum AccountMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::Other => 'Other',
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
