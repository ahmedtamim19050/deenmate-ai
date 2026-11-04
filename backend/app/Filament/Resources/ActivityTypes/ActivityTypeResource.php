<?php

namespace App\Filament\Resources\ActivityTypes;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\ActivityTypes\Pages\ManageActivityTypes;
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

class ActivityTypeResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = ActivityType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Activities & Points';

    protected static ?string $navigationLabel = 'Activity Types';

    protected static ?string $modelLabel = 'Activity Type';

    protected static ?string $pluralModelLabel = 'Activity Types';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

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
            TextInput::make('key')->required()->disabledOn('edit'),
            TextInput::make('name')->required(),
            TextInput::make('category')->required(),
            TextInput::make('unit')->required(),
            TextInput::make('max_quantity')->numeric()->minValue(1)->required(),
            Toggle::make('allows_in_app_source'),
            Toggle::make('allows_manual_source'),
            Toggle::make('one_per_day'),
            Toggle::make('client_creatable'),
            Toggle::make('is_active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('key')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('category')->badge(),
                TextColumn::make('unit'),
                IconColumn::make('allows_in_app_source')->boolean(),
                IconColumn::make('allows_manual_source')->boolean(),
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
            'index' => ManageActivityTypes::route('/'),
        ];
    }
}
