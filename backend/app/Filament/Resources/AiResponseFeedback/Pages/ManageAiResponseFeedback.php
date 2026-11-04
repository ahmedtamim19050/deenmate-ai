<?php

namespace App\Filament\Resources\AiResponseFeedback\Pages;

use App\Filament\Resources\AiResponseFeedback\AiResponseFeedbackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAiResponseFeedback extends ManageRecords
{
    protected static string $resource = AiResponseFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return AiResponseFeedbackResource::canCreate() ? [CreateAction::make()] : [];
    }
}
