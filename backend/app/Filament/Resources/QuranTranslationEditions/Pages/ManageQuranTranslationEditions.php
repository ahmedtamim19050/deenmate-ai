<?php

namespace App\Filament\Resources\QuranTranslationEditions\Pages;

use App\Filament\Resources\QuranTranslationEditions\QuranTranslationEditionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuranTranslationEditions extends ManageRecords
{
    protected static string $resource = QuranTranslationEditionResource::class;

    protected function getHeaderActions(): array
    {
        return QuranTranslationEditionResource::canCreate() ? [CreateAction::make()] : [];
    }
}
