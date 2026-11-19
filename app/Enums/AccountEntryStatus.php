<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status workflow shared by account transactions and account transfers:
 * entries are requested as Pending, and only Completed entries move money.
 */
enum AccountEntryStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting Approval',
            self::Completed => 'Completed',
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
