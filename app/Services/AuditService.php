<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Record an audit event.
     *
     * @param  string      $event    e.g. 'created', 'updated', 'deleted', 'posted', 'voided'
     * @param  Model|null  $model    The entity being acted on
     * @param  array       $old      Previous values (for updates)
     * @param  array       $new      New values
     * @param  string|null $description  Human-readable summary
     */
    public static function log(
        string  $event,
        ?Model  $model       = null,
        array   $old         = [],
        array   $new         = [],
        ?string $description = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'         => Auth::id(),
            'event'           => $event,
            'auditable_type'  => $model ? get_class($model) : null,
            'auditable_id'    => $model?->getKey(),
            'old_values'      => $old  ?: null,
            'new_values'      => $new  ?: null,
            'ip_address'      => Request::ip(),
            'user_agent'      => Request::userAgent(),
            'description'     => $description,
        ]);
    }

    /**
     * Convenience method to log a model creation.
     */
    public static function created(Model $model, ?string $description = null): AuditLog
    {
        return self::log('created', $model, [], $model->toArray(), $description);
    }

    /**
     * Convenience method to log a model update.
     */
    public static function updated(Model $model, array $original, ?string $description = null): AuditLog
    {
        return self::log('updated', $model, $original, $model->getChanges(), $description);
    }

    /**
     * Convenience method to log a model deletion.
     */
    public static function deleted(Model $model, ?string $description = null): AuditLog
    {
        return self::log('deleted', $model, $model->toArray(), [], $description);
    }

    /**
     * Log posting of a transaction document.
     */
    public static function posted(Model $model, ?string $description = null): AuditLog
    {
        return self::log('posted', $model, [], [], $description);
    }

    /**
     * Log voiding/reversal of a transaction.
     */
    public static function voided(Model $model, ?string $description = null): AuditLog
    {
        return self::log('voided', $model, [], [], $description);
    }
}
