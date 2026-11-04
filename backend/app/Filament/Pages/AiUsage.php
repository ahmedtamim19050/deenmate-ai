<?php

namespace App\Filament\Pages;

use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Contracts\LlmProvider;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AiUsage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'AI Usage';

    protected static ?string $title = 'AI Usage Analytics';

    protected string $view = 'filament.pages.ai-usage';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAdminRole(User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN) ?? false;
    }

    protected function getViewData(): array
    {
        return [
            'provider' => app(LlmProvider::class),
            'model' => config('deenmate.ai.model'),
            // Aggregates only: never message text and never per-user figures.
            'days' => AiMessage::query()
                ->where('role', 'assistant')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as day, COUNT(*) as answers, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, ROUND(AVG(latency_ms)) as latency')
                ->groupBy('day')
                ->orderByDesc('day')
                ->get(),
            'statuses' => AiMessage::query()
                ->where('role', 'assistant')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }
}
