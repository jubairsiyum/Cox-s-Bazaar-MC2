<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Payment status of a sales invoice. Unlike the shared purchasing
 * PaymentStatus this carries a Cancelled state for voided invoices.
 */
enum SalesPaymentStatus: string
{
    case Due = 'due';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Due',
            self::Partial => 'Partial',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Badge key used by the shared <x-status-badge> component.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Due => 'due',
            self::Partial => 'partial',
            self::Paid => 'paid',
            self::Cancelled => 'cancelled',
        };
    }

    /**
     * Derive the payment status from the invoice's settled amounts.
     */
    public static function derive(SalesInvoiceStatus $status, float|int|string|null $paid, float|int|string|null $due): self
    {
        if ($status === SalesInvoiceStatus::Voided) {
            return self::Cancelled;
        }

        $dueAmount = round((float) ($due ?? 0), 2);
        $paidAmount = round((float) ($paid ?? 0), 2);

        if ($dueAmount <= 0) {
            return self::Paid;
        }

        return $paidAmount > 0 ? self::Partial : self::Due;
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
