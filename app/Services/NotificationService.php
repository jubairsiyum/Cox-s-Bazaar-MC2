<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
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
    public static function notify(User $user, string $title, string $body = '', ?string $url = null, string $type = 'info'): void
    {
        $user->notify(new GenericDatabaseNotification($title, $body, $url, $type));
    }

    /**
     * Notify several users at once, skipping nulls and duplicates.
     *
     * @param  Collection<int, User>|array<int, User|null>  $users
     */
    public static function notifyMany(iterable $users, string $title, string $body = '', ?string $url = null, string $type = 'info'): void
    {
        $seen = [];

        foreach ($users as $user) {
            if (! $user instanceof User || isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;

            self::notify($user, $title, $body, $url, $type);
        }
    }
}
