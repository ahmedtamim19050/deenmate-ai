<?php

namespace App\Filament\Resources\NotificationConfigs\Pages;

use App\Filament\Resources\NotificationConfigs\NotificationConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNotificationConfigs extends ManageRecords
{
    protected static string $resource = NotificationConfigResource::class;

    protected function getHeaderActions(): array
    {
        return NotificationConfigResource::canCreate() ? [CreateAction::make()] : [];
    }
}
