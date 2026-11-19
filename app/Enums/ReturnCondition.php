<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Physical condition of a product returned by a customer.
 */
enum ReturnCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Damaged => 'Damaged',
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
