<?php

namespace App\Filament\Resources\QuranVerses\Pages;

use App\Filament\Resources\QuranVerses\QuranVerseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuranVerses extends ManageRecords
{
    protected static string $resource = QuranVerseResource::class;

    protected function getHeaderActions(): array
    {
        return QuranVerseResource::canCreate() ? [CreateAction::make()] : [];
    }
}
