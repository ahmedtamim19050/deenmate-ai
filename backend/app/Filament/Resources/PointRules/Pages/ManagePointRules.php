<?php

namespace App\Filament\Resources\PointRules\Pages;

use App\Filament\Resources\PointRules\PointRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePointRules extends ManageRecords
{
    protected static string $resource = PointRuleResource::class;

    protected function getHeaderActions(): array
    {
        return PointRuleResource::canCreate() ? [CreateAction::make()] : [];
    }
}
