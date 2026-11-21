<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Thin wrapper over database notifications so every module sends
 * in-app alerts consistently (bell dropdown + notifications center).
 */
class NotificationService
{
    /**
     * Notify a single user.
     */
    public static function notify(User $user, string $title, string $body = '', ?string $url = null, string $type = 'info', ?int $locationId = null): void
    {
        $user->notify(new GenericDatabaseNotification($title, $body, $url, $type, $locationId));
    }

    /**
     * Notify a supplier's profile feed. The notification is addressed to the
     * supplier row and rendered on the supplier profile. When the supplier has
     * a linked Supplier-role login, the same alert also lands in that login's
     * bell. Entries can be tagged with the branch (location) they happened at
     * so strict branch isolation can filter the feed per viewer.
     */
    public static function notifySupplier(Supplier $supplier, string $title, string $body = '', ?string $url = null, string $type = 'info', ?int $locationId = null): void
    {
        $supplier->notify(new GenericDatabaseNotification($title, $body, $url, $type, $locationId));

        $login = $supplier->user;

        if ($login !== null) {
            $login->notify(new GenericDatabaseNotification($title, $body, $url, $type, $locationId));
        }
    }

    /**
     * Notify several users at once, skipping nulls and duplicates.
     *
     * @param  Collection<int, User>|array<int, User|null>  $users
     */
    public static function notifyMany(iterable $users, string $title, string $body = '', ?string $url = null, string $type = 'info', ?int $locationId = null): void
    {
        $seen = [];

        foreach ($users as $user) {
            if (! $user instanceof User || isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;

            self::notify($user, $title, $body, $url, $type, $locationId);
        }
    }

    /**
     * Strict branch isolation on read: branch-tagged notification entries
     * only surface for users of the matching branch; personal entries
     * without a branch tag stay visible. The Super Admin sees everything.
     * A supplier login reads only alerts addressed to it personally, so its
     * branch-tagged supplier alerts are never filtered out.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeFor($query, ?User $user)
    {
        if ($user === null || BranchScope::isUnrestricted($user) || $user->isSupplierPortalUser()) {
            return $query;
        }

        return $query->where(function ($q) use ($user): void {
            $q->whereNull('data->location_id')
                ->orWhere('data->location_id', BranchScope::locationId($user));
        });
    }
}
