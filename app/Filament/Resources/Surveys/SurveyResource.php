<?php

namespace App\Filament\Resources\Surveys;

use App\Filament\Resources\Surveys\Pages\ManageSurveys;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Modules\Surveys\Actions\CloseSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Survey & Analisis';

    protected static ?string $navigationLabel = 'Survey';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->label('Research Project')
                    ->options(fn (): array => self::manageableProjectOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Status')
                    ->options(self::statusOptions())
                    ->default(Survey::STATUS_DRAFT)
                    ->required()
                    ->in(Survey::STATUSES),
                Select::make('identity_mode')
                    ->label('Identity Mode')
                    ->options(self::identityModeOptions())
                    ->default(Survey::IDENTITY_HIDDEN)
                    ->required()
                    ->in(Survey::IDENTITY_MODES),
                Toggle::make('is_public')
                    ->label('Public survey link')
                    ->helperText('Only published public surveys can receive public responses.')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No surveys yet')
            ->emptyStateDescription('Create your first research survey to collect responses, evaluate instruments, and generate descriptive analysis.')
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
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        Survey::STATUS_PUBLISHED => 'success',
                        Survey::STATUS_CLOSED => 'warning',
                        Survey::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('identity_mode')
                    ->label('Identity')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::identityModeOptions()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
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
                    ->options(fn (): array => self::visibleProjectOptions()),
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
                SelectFilter::make('identity_mode')
                    ->options(self::identityModeOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => self::validatedProjectScopedData($data))
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('update', $record) ?? false),
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
                Action::make('responses')
                    ->label('Responses')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('view', $record) ?? false)
                    ->url(fn (Survey $record): string => route('admin.surveys.responses.index', ['survey' => $record])),
                Action::make('analysis')
                    ->label('Analysis Dashboard')
                    ->icon('heroicon-o-chart-bar')
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('runAnalysis', $record) ?? false)
                    ->url(fn (Survey $record): string => route('admin.surveys.analysis.index', ['survey' => $record])),
                Action::make('builder')
                    ->label('Builder')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->url(fn (Survey $record): string => route('admin.surveys.builder.index', ['survey' => $record])),
                Action::make('distribution')
                    ->label('Distribution Center')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->url(fn (Survey $record): string => route('admin.surveys.distribution.index', ['survey' => $record])),
                Action::make('scoring')
                    ->label('Scoring')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('manageScoring', $record) ?? false)
                    ->url(fn (Survey $record): string => route('admin.surveys.scoring.index', ['survey' => $record])),
                Action::make('validation')
                    ->label('Expert Validation')
                    ->icon('heroicon-o-academic-cap')
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('manageValidation', $record) ?? false)
                    ->url(fn (Survey $record): string => route('admin.surveys.validation.index', ['survey' => $record])),
                Action::make('readability')
                    ->label('Readability Test')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('manageValidation', $record) ?? false)
                    ->url(fn (Survey $record): string => route('admin.surveys.readability.index', ['survey' => $record])),
                DeleteAction::make()
                    ->visible(fn (Survey $record): bool => auth()->user()?->can('delete', $record) ?? false),
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
        return auth()->user()?->can('create', Survey::class) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function validatedProjectScopedData(array $data): array
    {
        $projectId = $data['project_id'] ?? null;
        $project = filled($projectId) ? ResearchProject::query()->find($projectId) : null;
        $user = auth()->user();

        if (! $user || ! $project || ! $user->can('update', $project)) {
            throw ValidationException::withMessages([
                'project_id' => 'Select a research project you are allowed to manage.',
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public static function visibleProjectOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return ResearchProject::query()
            ->visibleTo($user)
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function manageableProjectOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return ResearchProject::query()
            ->visibleTo($user)
            ->orderBy('title')
            ->get()
            ->filter(fn (ResearchProject $project): bool => $user->can('update', $project))
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            Survey::STATUS_DRAFT => 'Draft',
            Survey::STATUS_PUBLISHED => 'Published',
            Survey::STATUS_CLOSED => 'Closed',
            Survey::STATUS_ARCHIVED => 'Archived',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function identityModeOptions(): array
    {
        return [
            Survey::IDENTITY_FULL => 'Full Identity',
            Survey::IDENTITY_HIDDEN => 'Hidden Identity',
            Survey::IDENTITY_ANONYMOUS => 'Anonymous',
            Survey::IDENTITY_PSEUDONYM => 'Pseudonym',
        ];
    }
}
