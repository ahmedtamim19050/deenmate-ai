<?php

namespace App\Filament\Resources\Achievements;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\Achievements\Pages\ManageAchievements;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\Achievement;
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

class AchievementResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = Achievement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Activities & Points';

    protected static ?string $navigationLabel = 'Achievements';

    protected static ?string $modelLabel = 'Achievement';

    protected static ?string $pluralModelLabel = 'Achievements';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR];

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
            TextInput::make('key')->required()->disabledOn('edit'),
            TextInput::make('name')->required(),
            TextInput::make('description')->required()->helperText('Describe app activity only, never spiritual status.'),
            TextInput::make('icon')->required(),
            Toggle::make('is_active'),
            Repeater::make('rules')->relationship()->schema([Select::make('rule_type')->options(['activity_count' => 'Activity count', 'activity_quantity' => 'Activity quantity', 'goal_completed' => 'Goal completed'])->required(), TextInput::make('activity_type_key'), TextInput::make('threshold')->numeric()->minValue(1)->default(1)->required()])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('key')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('description')->limit(60),
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
            'index' => ManageAchievements::route('/'),
        ];
    }
}
