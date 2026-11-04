<?php

namespace App\Filament\Resources\HadithCollections\Pages;

use App\Filament\Resources\HadithCollections\HadithCollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHadithCollections extends ManageRecords
{
    protected static string $resource = HadithCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return HadithCollectionResource::canCreate() ? [CreateAction::make()] : [];
    }
}
