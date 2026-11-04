<?php

namespace App\Filament\Resources\AiResponseFeedback;

use App\Filament\Concerns\RoleGatedResource;
use App\Filament\Resources\AiResponseFeedback\Pages\ManageAiResponseFeedback;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\User;
use App\Models\AiResponseFeedback;
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

class AiResponseFeedbackResource extends Resource
{
    use RoleGatedResource;

    protected static ?string $model = AiResponseFeedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'AI Feedback';

    protected static ?string $modelLabel = 'AI Feedback';

    protected static ?string $pluralModelLabel = 'AI Feedback';

    protected static ?string $slug = 'ai-feedback';

    protected static array $viewRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_SCHOLAR_REVIEWER];

    protected static array $manageRoles = [User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_SCHOLAR_REVIEWER];

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
            ->description('What people reported about answers. The person who sent it is deliberately not shown.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('rating')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('message.content')->label('Response')->limit(90)->wrap(),
                TextColumn::make('comment')->limit(60)->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('rating')->options(['helpful' => 'Helpful', 'not_helpful' => 'Not helpful', 'incorrect' => 'Incorrect']),
                SelectFilter::make('status')->options(['open' => 'Open', 'reviewed' => 'Reviewed']),
            ])
            ->recordActions([
                \App\Filament\Concerns\AiActions::markFeedbackReviewed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiResponseFeedback::route('/'),
        ];
    }
}
