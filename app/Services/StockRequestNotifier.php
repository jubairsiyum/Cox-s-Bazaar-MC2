<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * In-app alerts for the stock request lifecycle: a branch asks another
 * location (the warehouse or a brand shop) for stock, the source approves or
 * rejects it, and the requesting branch hears how it went.
 *
 * Every alert is tagged with the branch it concerns, so strict branch
 * isolation (NotificationService::scopeFor) keeps it inside that branch.
 */
class StockRequestNotifier
{
    /**
     * A new request: the source's approvers must decide, and the requester
     * gets an acknowledgement.
     */
    public static function submitted(StockTransfer $transfer): void
    {
        $url = route('stock-transfers.show', $transfer);
        $summary = self::summary($transfer);

        NotificationService::notifyMany(
            self::approversFor($transfer),
            "Stock request '{$transfer->reference_no}' awaits your approval.",
            self::fromName($transfer).' requested '.$summary.' from you. Submitted by '.($transfer->creator?->name ?? 'a branch manager').'.',
            $url,
            'warning',
            $transfer->from_location_id
        );

        if ($transfer->creator !== null) {
            NotificationService::notify(
                $transfer->creator,
                "Stock request '{$transfer->reference_no}' submitted.",
                'Your request to '.self::fromName($transfer).' for '.$summary.' is awaiting approval.',
                $url,
                'info',
                $transfer->to_location_id
            );
        }
    }

    /**
     * Approved and received: the requesting branch's managers and the
     * requester learn that the stock has arrived.
     */
    public static function approved(StockTransfer $transfer, ?User $approver): void
    {
        self::notifyRequestingBranch(
            $transfer,
            "Stock request '{$transfer->reference_no}' approved and received.",
            self::fromName($transfer).' approved your request and sent '.self::summary($transfer).' to '.self::toName($transfer).' ('.($approver?->name ?? 'the source manager').').',
            'success'
        );
    }

    /**
     * Rejected: the requesting branch's managers and the requester learn the
     * reason. No stock has moved.
     */
    public static function rejected(StockTransfer $transfer, ?User $approver, string $reason): void
    {
        self::notifyRequestingBranch(
            $transfer,
            "Stock request '{$transfer->reference_no}' rejected.",
            self::fromName($transfer).' rejected your request ('.($approver?->name ?? 'the source manager').'). Reason: '.$reason,
            'error'
        );
    }

    /**
     * Withdrawn by the requesting branch: the source's approvers stop waiting
     * for a decision, and the requesting branch's managers get a confirmation.
     */
    public static function withdrawn(StockTransfer $transfer, ?User $actor): void
    {
        NotificationService::notifyMany(
            self::approversFor($transfer),
            "Stock request '{$transfer->reference_no}' withdrawn.",
            self::toName($transfer).' withdrew the stock request for '.self::summary($transfer).'. No decision is needed.',
            route('stock-transfers.show', $transfer),
            'info',
            (int) $transfer->from_location_id
        );

        self::notifyRequestingBranch(
            $transfer,
            "Stock request '{$transfer->reference_no}' withdrawn.",
            'Your stock request from '.self::fromName($transfer).' was withdrawn ('.($actor?->name ?? 'the requester').').',
            'info'
        );
    }

    /**
     * The users who must decide a request: the source location's managers
     * holding the approve permission, plus the Super Admin. The requester is
     * never an approver of their own request.
     *
     * @return Collection<int, User>
     */
    public static function approversFor(StockTransfer $transfer): Collection
    {
        $managers = ManagerLookup::managersFor($transfer->from_location_id)
            ->filter(fn (User $user): bool => $user->can('stock-transfers.approve'));

        $superAdmins = User::query()->active()->role('Super Admin')->get();

        return $managers
            ->merge($superAdmins)
            ->reject(fn (User $user): bool => $user->id === $transfer->created_by)
            ->unique('id')
            ->values();
    }

    private static function notifyRequestingBranch(StockTransfer $transfer, string $title, string $body, string $type): void
    {
        NotificationService::notifyMany(
            ManagerLookup::managersFor($transfer->to_location_id)->push($transfer->creator),
            $title,
            $body,
            route('stock-transfers.show', $transfer),
            $type,
            $transfer->to_location_id
        );
    }

    private static function summary(StockTransfer $transfer): string
    {
        $units = (int) $transfer->items()->sum('quantity');
        $lines = $transfer->items()->count();

        return "{$units} unit(s) across {$lines} product line(s)";
    }

    private static function fromName(StockTransfer $transfer): string
    {
        return $transfer->fromLocation?->name ?? 'Unknown location';
    }

    private static function toName(StockTransfer $transfer): string
    {
        return $transfer->toLocation?->name ?? 'Unknown location';
    }
}
