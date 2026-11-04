<?php

namespace App\Filament\Resources\HadithRecords\Pages;

use App\Filament\Resources\HadithRecords\HadithRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHadithRecords extends ManageRecords
{
    protected static string $resource = HadithRecordResource::class;

    protected function getHeaderActions(): array
    {
        return HadithRecordResource::canCreate() ? [CreateAction::make()] : [];
    }
}
