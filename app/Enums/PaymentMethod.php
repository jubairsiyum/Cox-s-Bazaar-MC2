<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';
    case Bank = 'bank';
    case Cheque = 'cheque';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Rocket => 'Rocket',
            self::Bank => 'Bank Transfer',
            self::Cheque => 'Cheque',
            self::Card => 'Card',
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
