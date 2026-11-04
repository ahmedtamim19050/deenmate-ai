<?php

namespace App\Filament\Resources\ScholarReviews;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\ScholarReviews\Pages\ManageScholarReviews;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\ScholarReview;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ScholarReviewResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = ScholarReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Scholar Review';

    protected static ?string $navigationLabel = 'Scholar Reviews';

    protected static ?string $modelLabel = 'Scholar Review';

    protected static ?string $pluralModelLabel = 'Scholar Reviews';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SCHOLAR_REVIEWER, User::ROLE_CONTENT_EDITOR];

    protected static array $manageRoles = [];

    protected static bool $creatable = false;

    protected static bool $editable = false;

    protected static bool $deletable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('The review trail. Entries are added by the review actions on sources, courses and lessons.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reviewable_type')->label('Item')->formatStateUsing(fn ($state) => class_basename($state)),
                TextColumn::make('reviewable_id')->label('Id'),
                TextColumn::make('status')->badge(),
                TextColumn::make('reviewer.name')->label('Reviewer'),
                TextColumn::make('notes')->limit(60)->placeholder('-'),
                TextColumn::make('reviewed_at')->dateTime()->sortable(),
            ])
            ->filters([

            ])
            ->recordActions([

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageScholarReviews::route('/'),
        ];
    }
}
