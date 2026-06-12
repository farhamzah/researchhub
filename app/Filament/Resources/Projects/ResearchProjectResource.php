<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\ManageResearchProjects;
use App\Models\ResearchProject;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ResearchProjectResource extends Resource
{
    protected static ?string $model = ResearchProject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Projects';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->default(ResearchProject::STATUS_DRAFT)
                    ->required()
                    ->in(ResearchProject::STATUSES),
                DatePicker::make('started_at')
                    ->label('Started at')
                    ->native(false),
                DatePicker::make('target_finished_at')
                    ->label('Target finish')
                    ->native(false)
                    ->afterOrEqual('started_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No research projects yet')
            ->emptyStateDescription('Create your first research project to organize documents, surveys, analysis, and timeline milestones.')
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        ResearchProject::STATUS_ACTIVE => 'success',
                        ResearchProject::STATUS_PAUSED => 'warning',
                        ResearchProject::STATUS_COMPLETED => 'info',
                        ResearchProject::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('started_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('target_finished_at')
                    ->label('Target Finish')
                    ->date()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (ResearchProject $record): bool => auth()->user()?->can('update', $record) ?? false),
                Action::make('timeline')
                    ->label('Open Timeline')
                    ->icon('heroicon-o-calendar-days')
                    ->visible(fn (ResearchProject $record): bool => auth()->user()?->can('viewTimeline', $record) ?? false)
                    ->url(fn (ResearchProject $record): string => route('admin.projects.timeline.index', ['researchProject' => $record])),
                Action::make('validators')
                    ->label('Validators')
                    ->icon('heroicon-o-academic-cap')
                    ->visible(fn (ResearchProject $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->url(fn (ResearchProject $record): string => route('admin.projects.validators.index', ['researchProject' => $record])),
                Action::make('supervision')
                    ->label('Supervision')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn (ResearchProject $record): bool => auth()->user()?->can('viewSupervision', $record) ?? false)
                    ->url(fn (ResearchProject $record): string => route('admin.projects.supervision.index', ['researchProject' => $record])),
                DeleteAction::make()
                    ->visible(fn (ResearchProject $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResearchProjects::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        if (! auth()->check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with('owner')
            ->visibleTo(auth()->user());
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', ResearchProject::class) ?? false;
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
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            ResearchProject::STATUS_DRAFT => 'Draft',
            ResearchProject::STATUS_ACTIVE => 'Active',
            ResearchProject::STATUS_PAUSED => 'Paused',
            ResearchProject::STATUS_COMPLETED => 'Completed',
            ResearchProject::STATUS_ARCHIVED => 'Archived',
        ];
    }
}
