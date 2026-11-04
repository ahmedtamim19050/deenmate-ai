<?php

namespace App\Filament\Resources\AppSettings;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\AppSettings\Pages\ManageAppSettings;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\AppSetting;
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

class AppSettingResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = AppSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Application Settings';

    protected static ?string $modelLabel = 'Application Setting';

    protected static ?string $pluralModelLabel = 'Application Settings';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

    protected static bool $creatable = false;

    protected static bool $editable = true;

    protected static bool $deletable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('group', 'general');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->disabled(),
            Textarea::make('value')->rows(3)->columnSpanFull(),
            TextInput::make('description')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key', 'asc')
            ->columns([
                TextColumn::make('key')->searchable(),
                TextColumn::make('value')->limit(50)->placeholder('(empty)'),
                TextColumn::make('description')->limit(70)->wrap(),
                IconColumn::make('is_public')->boolean(),
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
            'index' => ManageAppSettings::route('/'),
        ];
    }
}
