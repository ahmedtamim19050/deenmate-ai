<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Every admin resource declares which roles may see and change it. There is deliberately
 * no resource at all for Salah records, activity logs or the per-user points ledger.
 */
trait RoleGatedResource
{
    private static function current(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function canAccess(): bool
    {
        return (bool) self::current()?->hasAdminRole(...static::$viewRoles);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    protected static function canManage(): bool
    {
        return static::$manageRoles !== [] && (bool) self::current()?->hasAdminRole(...static::$manageRoles);
    }

    public static function canCreate(): bool
    {
        return static::$creatable && static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::$editable && static::canManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::$deletable && static::canManage();
    }

    public static function canDeleteAny(): bool
    {
        return static::$deletable && static::canManage();
    }
}
