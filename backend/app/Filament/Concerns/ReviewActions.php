<?php

namespace App\Filament\Concerns;

use App\Models\AuditLog;
use App\Models\IslamicSource;
use App\Models\ScholarReview;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;

class ReviewActions
{
    private static function reviewer(): bool
    {
        return auth()->user()?->hasAdminRole(User::ROLE_SCHOLAR_REVIEWER, User::ROLE_SUPER_ADMIN) ?? false;
    }

    private static function schema(): array
    {
        return [
            Select::make('status')->options(['approved' => 'Approve', 'rejected' => 'Reject', 'pending' => 'Return to pending'])->required(),
            Textarea::make('notes')->rows(3),
        ];
    }

    private static function trail(Model $record, array $data): void
    {
        ScholarReview::query()->create([
            'reviewable_type' => $record->getMorphClass(),
            'reviewable_id' => $record->getKey(),
            'reviewer_id' => auth()->id(),
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => now(),
        ]);
        AuditLog::record('review.'.$data['status'], $record, [], auth()->id());
    }

    /** For courses and lessons, which carry one review_status column. */
    public static function review(string $column): Action
    {
        return Action::make('review')
            ->icon('heroicon-o-check-badge')
            ->visible(fn () => self::reviewer())
            ->schema(self::schema())
            ->action(function (Model $record, array $data) use ($column) {
                $record->forceFill([$column => $data['status']])->save();
                self::trail($record, $data);
            });
    }

    public static function reviewSource(): Action
    {
        return Action::make('review')
            ->icon('heroicon-o-check-badge')
            ->visible(fn () => self::reviewer())
            ->requiresConfirmation()
            ->modalDescription('Approved sources become available to the AI assistant as authoritative knowledge.')
            ->schema(self::schema())
            ->action(function (IslamicSource $record, array $data) {
                $record->forceFill([
                    'verification_status' => $data['status'],
                    'reviewer_id' => auth()->id(),
                    'reviewed_at' => now(),
                    'review_notes' => $data['notes'] ?? null,
                ])->save();
                self::trail($record, $data);
            });
    }
}
