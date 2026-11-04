<?php

namespace App\Filament\Resources\IslamicSources\RelationManagers;

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChunksRelationManager extends RelationManager
{
    protected static string $relationship = 'chunks';

    protected static ?string $title = 'Passages';

    private function canChange(): bool
    {
        return auth()->user()?->hasAdminRole(User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_ADMIN, User::ROLE_CONTENT_EDITOR) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('chunk_index')->numeric()->required()->default(0),
            TextInput::make('reference')->required()->helperText('For example: Quran 2:255'),
            Textarea::make('content')->rows(8)->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('chunk_index')
            ->columns([
                TextColumn::make('chunk_index')->sortable(),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('content')->limit(100)->wrap()->searchable(),
            ])
            ->headerActions([CreateAction::make()->visible(fn () => $this->canChange())])
            ->recordActions([EditAction::make()->visible(fn () => $this->canChange())]);
    }
}
