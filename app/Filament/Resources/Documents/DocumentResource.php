<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ResearchProject;
use App\Modules\Documents\Actions\DeleteDocumentAction;
use App\Modules\Documents\Actions\UpdateDocumentAction;
use App\Modules\Documents\Services\DocumentFileNameSuggestionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Research Documents';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->label('Project')
                    ->options(fn (): array => self::manageableProjectOptions())
                    ->searchable()
                    ->required(),
                Select::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => self::categoryOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->label('Status')
                    ->options(self::statusOptions())
                    ->default(Document::STATUS_DRAFT)
                    ->required()
                    ->in(Document::STATUSES),
                Select::make('document_type')
                    ->label('Academic document type')
                    ->options(self::documentTypeOptions())
                    ->searchable()
                    ->nullable()
                    ->in(Document::TYPES),
                Select::make('visibility')
                    ->label('Visibility')
                    ->options(self::visibilityOptions())
                    ->default(Document::VISIBILITY_PRIVATE)
                    ->required()
                    ->in(Document::VISIBILITIES),
                TextInput::make('version_label')
                    ->label('Version label')
                    ->placeholder('v01')
                    ->maxLength(50),
                TextInput::make('version_number')
                    ->label('Version number')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
                Toggle::make('is_current')
                    ->label('Current active version')
                    ->default(true),
                TextInput::make('reviewer_name')
                    ->label('Reviewer / supervisor')
                    ->maxLength(255),
                DatePicker::make('reviewed_at')
                    ->label('Reviewed at'),
                DatePicker::make('revision_due_date')
                    ->label('Revision due date'),
                Placeholder::make('suggested_file_name')
                    ->label('Saran nama file')
                    ->content(fn (?Document $record): string => $record
                        ? app(DocumentFileNameSuggestionService::class)->suggest($record->loadMissing(['project', 'category']))
                        : 'Simpan dokumen untuk membuat saran nama file akademik.'),
                TextInput::make('next_action')
                    ->label('Next action')
                    ->placeholder('Kirim ulang ke pembimbing.')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('revision_summary')
                    ->label('Revision summary')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Description / notes')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No research documents yet')
            ->emptyStateDescription('Create your first research document record, such as a proposal, chapter draft, revision file, dataset, presentation, or poster.')
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.title')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (Document::TYPE_LABELS[$state] ?? self::label($state))
                        : 'Unclassified')
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Document::STATUS_LABELS[$state] ?? self::label($state))
                    ->color(fn (string $state): string => Document::STATUS_COLORS[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'private' => 'Private',
                        'project' => 'Project Members',
                        'review_link' => 'Review Link',
                        'public' => 'Public',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'private' => 'gray',
                        'project' => 'info',
                        'review_link' => 'warning',
                        'public' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('version_label')
                    ->label('Version')
                    ->state(fn (Document $record): string => $record->versionDisplay())
                    ->badge()
                    ->color('info'),
                TextColumn::make('is_current')
                    ->label('Current')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Current' : 'Older')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('revision_due_date')
                    ->label('Revision due')
                    ->date()
                    ->placeholder('No due date')
                    ->sortable(),
                TextColumn::make('next_action')
                    ->label('Next action')
                    ->limit(44)
                    ->placeholder('No next action'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label('Project')
                    ->options(fn (): array => self::visibleProjectOptions()),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
                SelectFilter::make('document_type')
                    ->label('Type')
                    ->options(self::documentTypeOptions()),
            ])
            ->recordActions([
                Action::make('reviewLinks')
                    ->label('Review Links')
                    ->icon('heroicon-o-link')
                    ->visible(fn (Document $record): bool => auth()->user()?->can('createReviewLink', $record) ?? false)
                    ->url(fn (Document $record): string => route('admin.documents.review-links.index', ['document' => $record])),
                EditAction::make()
                    ->using(fn (Document $record, array $data): Document => app(UpdateDocumentAction::class)->handle(
                        auth()->user(),
                        $record,
                        self::managedProjectFromForm($data),
                        self::categoryFromForm($data),
                        $data,
                    ))
                    ->visible(fn (Document $record): bool => auth()->user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->using(function (Document $record): void {
                        app(DeleteDocumentAction::class)->handle(auth()->user(), $record);
                    })
                    ->visible(fn (Document $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocuments::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        if (! auth()->check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with(['project', 'category', 'currentVersion'])
            ->visibleTo(auth()->user());
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && self::manageableProjectOptions() !== []
            && self::categoryOptions() !== [];
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return self::canCreate()
            ? Response::allow()
            : Response::deny('No manageable research project is available for document creation.');
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
            ->filter(fn (ResearchProject $project): bool => Gate::forUser($user)->allows('create', [Document::class, $project]))
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return DocumentCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(Document::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => Document::STATUS_LABELS[$status] ?? self::label($status)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function documentTypeOptions(): array
    {
        return collect(Document::TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => Document::TYPE_LABELS[$type] ?? self::label($type)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function visibilityOptions(): array
    {
        return collect(Document::VISIBILITIES)
            ->mapWithKeys(fn (string $visibility): array => [$visibility => match ($visibility) {
                Document::VISIBILITY_PRIVATE => 'Private',
                Document::VISIBILITY_PROJECT => 'Project Members',
                Document::VISIBILITY_REVIEW_LINK => 'Review Link',
                Document::VISIBILITY_PUBLIC => 'Public',
                default => self::label($visibility),
            }])
            ->all();
    }

    public static function managedProjectFromForm(array $data): ResearchProject
    {
        $project = ResearchProject::query()
            ->whereKey($data['project_id'] ?? null)
            ->first();

        if (! $project || ! array_key_exists($project->getKey(), self::manageableProjectOptions())) {
            throw ValidationException::withMessages([
                'project_id' => 'Select a project you are allowed to manage.',
            ]);
        }

        return $project;
    }

    public static function categoryFromForm(array $data): DocumentCategory
    {
        $category = DocumentCategory::query()
            ->whereKey($data['category_id'] ?? null)
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => 'Select a valid document category.',
            ]);
        }

        return $category;
    }

    private static function label(string $state): string
    {
        return ucfirst(str_replace('_', ' ', $state));
    }
}
