<?php

namespace App\Filament\Resources\AiPrompts\Pages;

use App\Filament\Resources\AiPrompts\AiPromptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAiPrompts extends ManageRecords
{
    protected static string $resource = AiPromptResource::class;

    protected function getHeaderActions(): array
    {
        return AiPromptResource::canCreate() ? [CreateAction::make()] : [];
    }
}
