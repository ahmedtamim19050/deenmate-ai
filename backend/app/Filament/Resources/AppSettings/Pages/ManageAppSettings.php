<?php

namespace App\Filament\Resources\AppSettings\Pages;

use App\Filament\Resources\AppSettings\AppSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAppSettings extends ManageRecords
{
    protected static string $resource = AppSettingResource::class;

    protected function getHeaderActions(): array
    {
        return AppSettingResource::canCreate() ? [CreateAction::make()] : [];
    }
}
