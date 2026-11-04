<?php

namespace App\Filament\Resources\IslamicSources;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\IslamicSources\Pages\ManageIslamicSources;
use App\Filament\Resources\IslamicSources\RelationManagers\ChunksRelationManager;
use App\Filament\Resources\IslamicSources\Pages\EditIslamicSource;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\IslamicSource;
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

class IslamicSourceResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = IslamicSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Scholar Review';

    protected static ?string $navigationLabel = 'Islamic Sources';

    protected static ?string $modelLabel = 'Islamic Source';

    protected static ?string $pluralModelLabel = 'Islamic Sources';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR, User::ROLE_SCHOLAR_REVIEWER];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR];

    protected static bool $creatable = true;

    protected static bool $editable = true;

    protected static bool $deletable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            Select::make('source_type')->options(['quran' => 'Quran', 'hadith' => 'Hadith', 'translation' => 'Translation', 'article' => 'Reviewed article', 'scholar_answer' => 'Scholar-reviewed answer'])->required(),
            TextInput::make('author'),
            TextInput::make('publisher'),
            TextInput::make('reference_identifier')->required(),
            TextInput::make('language')->default('en')->required(),
            TextInput::make('url')->url(),
            TextInput::make('license'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('The AI assistant can only use approved sources. Only a Scholar Reviewer or Super Administrator can approve one, and editing a passage returns the source to pending.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('source_type')->badge(),
                TextColumn::make('language'),
                TextColumn::make('verification_status')->badge(),
                TextColumn::make('reviewer.name')->label('Reviewer')->placeholder('-'),
                TextColumn::make('reviewed_at')->dateTime()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('verification_status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('edit')->label('Open')->icon('heroicon-o-pencil-square')->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
                \App\Filament\Concerns\ReviewActions::reviewSource(),
            ]);
    }

    public static function getRelations(): array
    {
        return [ChunksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIslamicSources::route('/'),
            'edit' => EditIslamicSource::route('/{record}/edit'),
        ];
    }
}
