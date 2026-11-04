<?php

namespace App\Filament\Resources\QuranTranslationEditions;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\QuranTranslationEditions\Pages\ManageQuranTranslationEditions;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\QuranTranslationEdition;
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

class QuranTranslationEditionResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = QuranTranslationEdition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Quran Translations';

    protected static ?string $modelLabel = 'Quran Translation';

    protected static ?string $pluralModelLabel = 'Quran Translations';

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
            TextInput::make('name')->required(),
            TextInput::make('translator'),
            TextInput::make('source_url')->url(),
            TextInput::make('license')->required()->helperText('Record the actual licence once it has been verified.'),
            Toggle::make('is_active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('identifier')->searchable(),
                TextColumn::make('language')->badge(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('license')->limit(50),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuranTranslationEditions::route('/'),
        ];
    }
}
