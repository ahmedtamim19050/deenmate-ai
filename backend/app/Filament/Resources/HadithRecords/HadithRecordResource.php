<?php

namespace App\Filament\Resources\HadithRecords;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\HadithRecords\Pages\ManageHadithRecords;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\HadithRecord;
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

class HadithRecordResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = HadithRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Hadith Records';

    protected static ?string $modelLabel = 'Hadith Record';

    protected static ?string $pluralModelLabel = 'Hadith Records';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR, User::ROLE_SCHOLAR_REVIEWER];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR];

    protected static bool $creatable = false;

    protected static bool $editable = true;

    protected static bool $deletable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('book'),
            TextInput::make('chapter'),
            Textarea::make('text_arabic')->rows(6)->columnSpanFull(),
            Textarea::make('translation')->rows(6)->columnSpanFull(),
            TextInput::make('grade')->helperText('Only a grade documented by the named source. Never estimate one.'),
            TextInput::make('grade_source'),
            TextInput::make('source_reference'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('collection.name')->label('Collection'),
                TextColumn::make('hadith_number')->searchable(),
                TextColumn::make('translation')->limit(70)->searchable(),
                TextColumn::make('grade')->placeholder('Not documented'),
                TextColumn::make('grade_source')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('hadith_collection_id')->relationship('collection', 'name')->label('Collection'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHadithRecords::route('/'),
        ];
    }
}
