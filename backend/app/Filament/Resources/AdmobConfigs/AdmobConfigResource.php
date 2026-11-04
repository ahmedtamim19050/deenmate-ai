<?php

namespace App\Filament\Resources\AdmobConfigs;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\AdmobConfigs\Pages\ManageAdmobConfigs;
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

class AdmobConfigResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = AppSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'AdMob Configuration';

    protected static ?string $modelLabel = 'AdMob Configuration';

    protected static ?string $pluralModelLabel = 'AdMob Configuration';

    protected static ?string $slug = 'admob-configuration';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN];

    protected static bool $creatable = false;

    protected static bool $editable = true;

    protected static bool $deletable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('group', 'admob');
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
            ->description('Placement switches only. Ad unit ids are build configuration. Ads never appear on Quran, Salah, Dua or Tasbih screens, whatever is set here.')
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
            'index' => ManageAdmobConfigs::route('/'),
        ];
    }
}
