<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a new product request: pending → approved (product created),
 * or rejected (nothing created).
 */
enum ProductRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
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
        };
    }
}
