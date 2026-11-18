<?php

declare(strict_types=1);

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case InTransit = 'in-transit';
    case Received = 'received';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InTransit => 'In Transit',
            self::Received => 'Received',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the goods have left the source location.
     */
    public function hasLeftSource(): bool
    {
        return $this === self::InTransit || $this === self::Received;
    }

    /**
     * Whether the goods have arrived at the destination location.
     */
    public function hasArrived(): bool
    {
        return $this === self::Received;
    }

    /**
     * Statuses a transfer may move to from the given status.
     *
     * @return array<int, self>
     */
    public static function transitionsFrom(self $from): array
    {
        return match ($from) {
            self::Draft => [self::Draft, self::InTransit, self::Received, self::Cancelled],
            self::InTransit => [self::InTransit, self::Received, self::Cancelled],
            self::Received => [self::Received, self::Cancelled],
            self::Rejected => [self::Rejected],
            self::Cancelled => [self::Cancelled],
        };
    }

    /**
     * Statuses a transfer may be created with (the sender picks one of these).
     *
     * @return array<int, self>
     */
    public static function creatable(): array
    {
        return [self::Draft, self::InTransit, self::Cancelled];
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
