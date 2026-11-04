<?php

namespace App\Filament\Resources\DataExportRequests;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\DataExportRequests\Pages\ManageDataExportRequests;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\DataExportRequest;
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

class DataExportRequestResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = DataExportRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Privacy Requests';

    protected static ?string $modelLabel = 'Privacy Request';

    protected static ?string $pluralModelLabel = 'Privacy Requests';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_SUPPORT_STAFF];

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
            ->description('Status of export requests only. The contents can only be downloaded by the person who asked for them.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.email')->label('User')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->placeholder('-'),
                TextColumn::make('expires_at')->dateTime()->placeholder('-'),
            ])
            ->filters([

            ])
            ->recordActions([

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDataExportRequests::route('/'),
        ];
    }
}
