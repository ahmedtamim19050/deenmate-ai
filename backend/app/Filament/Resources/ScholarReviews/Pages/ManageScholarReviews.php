<?php

namespace App\Filament\Resources\ScholarReviews\Pages;

use App\Filament\Resources\ScholarReviews\ScholarReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageScholarReviews extends ManageRecords
{
    protected static string $resource = ScholarReviewResource::class;

    protected function getHeaderActions(): array
    {
        return ScholarReviewResource::canCreate() ? [CreateAction::make()] : [];
    }
}
