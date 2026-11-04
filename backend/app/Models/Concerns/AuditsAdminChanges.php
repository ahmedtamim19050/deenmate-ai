<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Records create/update/delete made by a signed-in admin panel user.
 * API users never trigger this: they authenticate on the sanctum guard.
 */
trait AuditsAdminChanges
{
    protected static function bootAuditsAdminChanges(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                $actor = Auth::guard('web')->user();
                if ($actor === null || ! $actor->isAdmin()) {
                    return;
                }
                AuditLog::record(
                    'admin.'.class_basename($model).'.'.$event,
                    $model,
                    $event === 'updated' ? ['changed' => array_keys($model->getChanges())] : [],
                    $actor->id,
                );
            });
        }
    }
}
