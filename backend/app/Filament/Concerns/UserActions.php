<?php

namespace App\Filament\Concerns;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Points\PointsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class UserActions
{
    private static function superAdmin(): bool
    {
        return auth()->user()?->hasAdminRole(User::ROLE_SUPER_ADMIN) ?? false;
    }

    public static function changeRole(): Action
    {
        return Action::make('role')
            ->label('Admin role')
            ->icon('heroicon-o-key')
            ->visible(fn () => self::superAdmin())
            ->schema([Select::make('admin_role')->options(User::ROLES)->placeholder('App user (no admin access)')])
            ->action(function (User $record, array $data) {
                $record->forceFill(['admin_role' => $data['admin_role'] ?: null])->save();
                AuditLog::record('admin.role_assigned', $record, ['role' => $data['admin_role'] ?: null], auth()->id());
            });
    }

    public static function adjustPoints(): Action
    {
        return Action::make('adjustPoints')
            ->label('Adjust points')
            ->icon('heroicon-o-plus-circle')
            ->visible(fn () => self::superAdmin())
            ->schema([
                TextInput::make('points')->integer()->required()->helperText('Use a negative number to reduce. The balance never goes below zero.'),
                TextInput::make('reason')->required()->maxLength(190),
            ])
            ->action(function (User $record, array $data) {
                $tx = app(PointsService::class)->adminAdjust($record, (int) $data['points'], $data['reason'], auth()->user());
                AuditLog::record('admin.points_adjusted', $record, ['points' => $tx->points], auth()->id());
            });
    }
}
