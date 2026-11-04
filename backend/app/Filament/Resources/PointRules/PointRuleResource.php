<?php

namespace App\Filament\Resources\PointRules;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\PointRules\Pages\ManagePointRules;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\PointRule;
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

class PointRuleResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = PointRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Activities & Points';

    protected static ?string $navigationLabel = 'Point Rules';

    protected static ?string $modelLabel = 'Point Rule';

    protected static ?string $pluralModelLabel = 'Point Rules';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

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
            Select::make('activity_type_id')->options(ActivityType::query()->pluck('name', 'id'))->required(),
            TextInput::make('points')->numeric()->minValue(0)->required(),
            TextInput::make('per_quantity')->numeric()->minValue(1)->default(1)->required(),
            TextInput::make('daily_cap')->numeric()->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Rules are versioned. Saving a new rule supersedes the previous one, and ledger rows keep the version they used. Values are motivational app settings, not Islamic reward calculations.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('activityType.name')->label('Activity')->searchable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('points'),
                TextColumn::make('per_quantity'),
                TextColumn::make('daily_cap')->placeholder('None'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([

            ])
            ->recordActions([

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePointRules::route('/'),
        ];
    }
}
