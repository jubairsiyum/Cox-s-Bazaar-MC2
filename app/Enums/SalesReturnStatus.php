<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a sales return request:
 * pending → approved → completed (restocked), or rejected.
 */
enum SalesReturnStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Restocked & Completed',
        };
    }

    /**
     * Badge key used by the shared <x-status-badge> component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Approved => 'approved',
            self::Rejected => 'rejected',
            self::Completed => 'completed',
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
