<?php

namespace App\Filament\Resources\QuranSurahs\Pages;

use App\Filament\Resources\QuranSurahs\QuranSurahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuranSurahs extends ManageRecords
{
    protected static string $resource = QuranSurahResource::class;

    protected function getHeaderActions(): array
    {
        return QuranSurahResource::canCreate() ? [CreateAction::make()] : [];
    }
}
