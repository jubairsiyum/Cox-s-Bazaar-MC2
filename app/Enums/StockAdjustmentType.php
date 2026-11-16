<?php

declare(strict_types=1);

namespace App\Enums;

enum StockAdjustmentType: string
{
    case Normal = 'normal';
    case Abnormal = 'abnormal';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Abnormal => 'Abnormal',
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
