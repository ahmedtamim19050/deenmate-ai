<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLessons extends ManageRecords
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        return LessonResource::canCreate() ? [CreateAction::make()] : [];
    }
}
