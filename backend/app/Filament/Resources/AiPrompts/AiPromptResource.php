<?php

namespace App\Filament\Resources\AiPrompts;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\AiPrompts\Pages\ManageAiPrompts;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\AiPrompt;
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

class AiPromptResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = AiPrompt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'AI Prompts';

    protected static ?string $modelLabel = 'AI Prompt';

    protected static ?string $pluralModelLabel = 'AI Prompts';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_SCHOLAR_REVIEWER];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

    protected static bool $creatable = true;

    protected static bool $editable = false;

    protected static bool $deletable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->default('assistant_system'),
            TextInput::make('version')->numeric()->required(),
            Textarea::make('content')->rows(18)->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Prompts are versioned. Create a new version, then activate it. Older versions stay as a record.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('version')->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([

            ])
            ->recordActions([
                \App\Filament\Concerns\AiActions::activatePrompt(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiPrompts::route('/'),
        ];
    }
}
