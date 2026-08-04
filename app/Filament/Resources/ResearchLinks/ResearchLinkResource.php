<?php

namespace App\Filament\Resources\ResearchLinks;

use App\Filament\Resources\ResearchLinks\Pages\ManageResearchLinks;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Modules\ResearchLinks\Actions\DeleteResearchLinkAction;
use App\Modules\ResearchLinks\Actions\UpdateResearchLinkAction;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ResearchLinkResource extends Resource
{
    protected static ?string $model = ResearchLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Referensi Riset';

    protected static ?string $navigationLabel = 'Link Riset';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('research_project_id')
                    ->label('Research Project')
                    ->options(fn (): array => self::manageableProjectOptions())
                    ->searchable()
                    ->placeholder('Global link'),
                TextInput::make('title')
                    ->label('Site or resource name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->url()
                    ->maxLength(2048),
                Select::make('category')
                    ->label('Category')
                    ->options(ResearchLink::CATEGORY_LABELS)
                    ->default(ResearchLink::CATEGORY_OTHER)
                    ->required()
                    ->in(ResearchLink::CATEGORIES),
                Textarea::make('description')
                    ->label('Short description')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                TextInput::make('thumbnail_url')
                    ->label('Thumbnail URL')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('favicon_url')
                    ->label('Favicon URL')
                    ->url()
                    ->maxLength(2048),
                Toggle::make('is_pinned')
                    ->label('Pinned')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No research links yet')
            ->emptyStateDescription('Save useful research websites such as journals, OJS pages, regulations, datasets, repositories, and learning resources.')
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('preview')
                    ->label('Preview')
                    ->state(fn (ResearchLink $record): ?string => $record->thumbnail_url ?: $record->favicon_url)
                    ->square()
                    ->size(40),
                TextColumn::make('title')
                    ->label('Resource')
                    ->description(fn (ResearchLink $record): string => $record->description ? str($record->description)->limit(90)->toString() : 'No description')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ResearchLink::CATEGORY_LABELS[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        ResearchLink::CATEGORY_JOURNAL,
                        ResearchLink::CATEGORY_REFERENCE,
                        ResearchLink::CATEGORY_LEARNING_RESOURCE,
                        ResearchLink::CATEGORY_METHODOLOGY => 'info',
                        ResearchLink::CATEGORY_DATASET,
                        ResearchLink::CATEGORY_REPOSITORY,
                        ResearchLink::CATEGORY_GOOGLE_DRIVE => 'success',
                        ResearchLink::CATEGORY_OJS,
                        ResearchLink::CATEGORY_ETHICS,
                        ResearchLink::CATEGORY_REGULATION => 'warning',
                        ResearchLink::CATEGORY_AI_TOOL => 'purple',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('project.title')
                    ->label('Project')
                    ->placeholder('Global')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('url')
                    ->label('Domain')
                    ->formatStateUsing(fn (?string $state): string => parse_url((string) $state, PHP_URL_HOST) ?: 'Invalid URL')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('research_project_id')
                    ->label('Project')
                    ->options(fn (): array => self::visibleProjectOptions()),
                SelectFilter::make('category')
                    ->options(ResearchLink::CATEGORY_LABELS),
                SelectFilter::make('is_pinned')
                    ->label('Pinned')
                    ->options([
                        '1' => 'Pinned',
                        '0' => 'Not pinned',
                    ]),
                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open Link')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ResearchLink $record): string => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn (ResearchLink $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()
                    ->using(fn (ResearchLink $record, array $data): ResearchLink => app(UpdateResearchLinkAction::class)->handle(auth()->user(), $record, $data))
                    ->visible(fn (ResearchLink $record): bool => auth()->user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->using(function (ResearchLink $record): void {
                        app(DeleteResearchLinkAction::class)->handle(auth()->user(), $record);
                    })
                    ->visible(fn (ResearchLink $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResearchLinks::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        if (! auth()->check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with(['project', 'creator'])
            ->visibleTo(auth()->user())
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', ResearchLink::class) ?? false;
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
            ->filter(fn (ResearchProject $project): bool => Gate::forUser($user)->allows('update', $project))
            ->pluck('title', 'id')
            ->all();
    }
}
