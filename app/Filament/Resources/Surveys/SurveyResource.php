<?php

namespace App\Filament\Resources\Surveys;

use App\Filament\Resources\Surveys\Pages\ManageSurveys;
use App\Models\Survey;
use App\Modules\Surveys\Actions\CloseSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Surveys';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.title')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('identity_mode')
                    ->label('Identity')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                TextColumn::make('responses_count')
                    ->label('Responses')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'title'),
                SelectFilter::make('status')
                    ->options(array_combine(Survey::STATUSES, Survey::STATUSES)),
                SelectFilter::make('identity_mode')
                    ->options(array_combine(Survey::IDENTITY_MODES, Survey::IDENTITY_MODES)),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Survey $record): bool => $record->status === Survey::STATUS_DRAFT && (auth()->user()?->can('publish', $record) ?? false))
                    ->requiresConfirmation()
                    ->action(fn (Survey $record): Survey => app(PublishSurveyAction::class)->handle(auth()->user(), $record)),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (Survey $record): bool => $record->status === Survey::STATUS_PUBLISHED && (auth()->user()?->can('close', $record) ?? false))
                    ->requiresConfirmation()
                    ->action(fn (Survey $record): Survey => app(CloseSurveyAction::class)->handle(auth()->user(), $record)),
                Action::make('publicLink')
                    ->label('Public Link')
                    ->icon('heroicon-o-link')
                    ->visible(fn (Survey $record): bool => $record->canReceiveResponses())
                    ->url(fn (Survey $record): string => route('survey.show', ['survey' => $record->slug]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSurveys::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        if (! auth()->check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with('project')
            ->withCount('responses')
            ->visibleTo(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}
