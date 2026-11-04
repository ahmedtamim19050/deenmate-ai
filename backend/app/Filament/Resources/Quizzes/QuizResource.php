<?php

namespace App\Filament\Resources\Quizzes;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\Quizzes\Pages\ManageQuizzes;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\Quiz;
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

class QuizResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = Quiz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Quizzes';

    protected static ?string $modelLabel = 'Quizze';

    protected static ?string $pluralModelLabel = 'Quizzes';

    protected static ?string $slug = 'quizzes';

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
            Select::make('lesson_id')->options(Lesson::query()->pluck('title', 'id'))->required()->searchable(),
            TextInput::make('title')->required(),
            TextInput::make('pass_percent')->numeric()->minValue(1)->maxValue(100)->default(60)->required(),
            Repeater::make('questions')->relationship()->orderColumn('sort_order')->schema([Textarea::make('question')->required(), TagsInput::make('options')->required()->helperText('Order matters. The first option is index 0.'), TextInput::make('correct_index')->numeric()->minValue(0)->required(), Textarea::make('explanation')])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('lesson.title')->label('Lesson')->searchable(),
                TextColumn::make('pass_percent'),
                TextColumn::make('questions_count')->counts('questions')->label('Questions'),
            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuizzes::route('/'),
        ];
    }
}
