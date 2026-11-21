<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which entry types a transaction category may be used for.
 */
enum TransactionCategoryType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Both => 'Income & Expense',
        };
    }

    /**
     * Whether a category of this type may be used on the given entry type.
     */
    public function allowsEntry(?string $entryType): bool
    {
        return match ($this) {
            self::Both => true,
            default => $entryType === null || $entryType === '' || $this->value === $entryType,
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
