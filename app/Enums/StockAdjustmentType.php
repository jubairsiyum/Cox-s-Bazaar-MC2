<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three stock adjustment operations. The line quantity is always a plain
 * amount whose meaning is given by the operation:
 *  - Add:    quantity is added to stock (found, corrections upward)
 *  - Remove: quantity is taken out of stock (damage, loss, theft)
 *  - Set:    stock is corrected to the counted quantity (stock takes)
 */
enum StockAdjustmentType: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Set = 'set';

    public function label(): string
    {
        return match ($this) {
            self::Add => 'Add Stock',
            self::Remove => 'Remove Stock',
            self::Set => 'Set Stock (Count)',
        };
    }

    /**
     * Short explanation shown under the type selector on the form.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Add => 'Enter how many units to add to the current stock.',
            self::Remove => 'Enter how many units to remove from the current stock.',
            self::Set => 'Enter the counted quantity — stock will be set to this number.',
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
