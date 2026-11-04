<?php

namespace App\Filament\Resources\DataExportRequests\Pages;

use App\Filament\Resources\DataExportRequests\DataExportRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDataExportRequests extends ManageRecords
{
    protected static string $resource = DataExportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return DataExportRequestResource::canCreate() ? [CreateAction::make()] : [];
    }
}
