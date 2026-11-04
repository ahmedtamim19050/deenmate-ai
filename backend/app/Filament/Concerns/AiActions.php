<?php

namespace App\Filament\Concerns;

use App\Models\AiPrompt;
use App\Models\AiResponseFeedback;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;

class AiActions
{
    public static function activatePrompt(): Action
    {
        return Action::make('activate')
            ->icon('heroicon-o-bolt')
            ->visible(fn (AiPrompt $record) => ! $record->is_active && auth()->user()?->hasAdminRole(User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN))
            ->requiresConfirmation()
            ->action(function (AiPrompt $record) {
                DB::transaction(function () use ($record) {
                    AiPrompt::query()->where('name', $record->name)->update(['is_active' => false]);
                    $record->update(['is_active' => true]);
                });
            });
    }

    public static function markFeedbackReviewed(): Action
    {
        return Action::make('reviewed')
            ->label('Mark reviewed')
            ->icon('heroicon-o-check')
            ->visible(fn (AiResponseFeedback $record) => $record->status !== 'reviewed')
            ->action(fn (AiResponseFeedback $record) => $record->update(['status' => 'reviewed']));
    }
}
