<?php

namespace App\Filament\Resources\QuranSurahs;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\QuranSurahs\Pages\ManageQuranSurahs;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
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

class QuranSurahResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = QuranSurah::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Quran Surahs';

    protected static ?string $modelLabel = 'Quran Surah';

    protected static ?string $pluralModelLabel = 'Quran Surahs';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR, User::ROLE_SCHOLAR_REVIEWER];

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
            ->description('Imported with php artisan quran:import. Quran text is not edited by hand.')
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name_arabic'),
                TextColumn::make('name_transliterated')->searchable(),
                TextColumn::make('name_english')->searchable(),
                TextColumn::make('revelation_place')->badge(),
                TextColumn::make('verse_count'),
            ])
            ->filters([

            ])
            ->recordActions([

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuranSurahs::route('/'),
        ];
    }
}
