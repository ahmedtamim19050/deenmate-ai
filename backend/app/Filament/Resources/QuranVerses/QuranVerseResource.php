<?php

namespace App\Filament\Resources\QuranVerses;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\QuranVerses\Pages\ManageQuranVerses;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\QuranVerse;
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

class QuranVerseResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = QuranVerse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Quran Verses';

    protected static ?string $modelLabel = 'Quran Verse';

    protected static ?string $pluralModelLabel = 'Quran Verses';

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
            ->description('Read-only view of the imported text and its recorded source.')
            ->defaultSort('surah_id', 'asc')
            ->columns([
                TextColumn::make('surah_id')->label('Surah')->sortable(),
                TextColumn::make('verse_number')->label('Verse')->sortable(),
                TextColumn::make('text_arabic')->wrap(),
                TextColumn::make('transliteration')->limit(40),
                TextColumn::make('text_source')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('surah_id')->label('Surah')->options(QuranSurah::query()->orderBy('id')->pluck('name_transliterated', 'id')),
            ])
            ->recordActions([

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuranVerses::route('/'),
        ];
    }
}
