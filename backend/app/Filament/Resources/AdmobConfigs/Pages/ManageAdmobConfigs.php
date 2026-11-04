<?php

namespace App\Filament\Resources\AdmobConfigs\Pages;

use App\Filament\Resources\AdmobConfigs\AdmobConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAdmobConfigs extends ManageRecords
{
    protected static string $resource = AdmobConfigResource::class;

    protected function getHeaderActions(): array
    {
        return AdmobConfigResource::canCreate() ? [CreateAction::make()] : [];
    }
}
