<?php

namespace App\Filament\Widgets;

use App\Models\AiMessage;
use App\Models\AiResponseFeedback;
use App\Models\DataExportRequest;
use App\Models\IslamicSource;
use App\Models\Lesson;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Operational totals only. No worship figure appears on any admin screen. */
class AdminOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = [
            Stat::make('Sources awaiting review', IslamicSource::query()->where('verification_status', 'pending')->count()),
            Stat::make('Lessons awaiting review', Lesson::query()->where('review_status', 'pending')->count()),
            Stat::make('Open AI feedback', AiResponseFeedback::query()->where('status', 'open')->count()),
        ];

        if (auth()->user()?->hasAdminRole(User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_SUPPORT_STAFF)) {
            $stats[] = Stat::make('Registered users', User::query()->count());
            $stats[] = Stat::make('Pending export requests', DataExportRequest::query()->whereIn('status', ['pending', 'processing'])->count());
        }
        if (auth()->user()?->hasAdminRole(User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN)) {
            $stats[] = Stat::make('AI answers today', AiMessage::query()->where('role', 'assistant')->where('created_at', '>=', now()->startOfDay())->count());
        }

        return $stats;
    }
}
