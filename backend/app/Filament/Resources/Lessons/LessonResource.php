<?php

namespace App\Filament\Resources\Lessons;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\Lessons\Pages\ManageLessons;
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

class LessonResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Lessons';

    protected static ?string $modelLabel = 'Lesson';

    protected static ?string $pluralModelLabel = 'Lessons';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR, User::ROLE_SCHOLAR_REVIEWER];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR];

    protected static bool $creatable = true;

    protected static bool $editable = true;

    protected static bool $deletable = true;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')->options(Course::query()->pluck('title', 'id'))->required(),
            TextInput::make('title')->required(),
            Textarea::make('body')->rows(14)->required()->columnSpanFull(),
            TagsInput::make('references')->columnSpanFull(),
            TextInput::make('audio_url')->url(),
            TextInput::make('duration_minutes')->numeric()->default(5),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_published'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('course.title')->label('Course')->searchable(),
                TextColumn::make('review_status')->badge(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('course_id')->relationship('course', 'title'),
                SelectFilter::make('review_status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            ])
            ->recordActions([
                EditAction::make(),
                \App\Filament\Concerns\ReviewActions::review('review_status'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLessons::route('/'),
        ];
    }
}
