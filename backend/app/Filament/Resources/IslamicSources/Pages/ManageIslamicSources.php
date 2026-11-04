<?php

namespace App\Filament\Resources\IslamicSources\Pages;

use App\Filament\Resources\IslamicSources\IslamicSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIslamicSources extends ManageRecords
{
    protected static string $resource = IslamicSourceResource::class;

    protected function getHeaderActions(): array
    {
        return IslamicSourceResource::canCreate() ? [CreateAction::make()] : [];
    }
}
