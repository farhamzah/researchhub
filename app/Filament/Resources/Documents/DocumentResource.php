<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ResearchProject;
use App\Modules\Documents\Actions\DeleteDocumentAction;
use App\Modules\Documents\Actions\UpdateDocumentAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                Select::make('visibility')
                    ->label('Visibility')
                    ->options(self::visibilityOptions())
                    ->default(Document::VISIBILITY_PRIVATE)
                    ->required()
                    ->in(Document::VISIBILITIES),
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
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'under_review' => 'Under Review',
                        'revision_required' => 'Revision Required',
                        'approved' => 'Approved',
                        'final' => 'Final',
                        'archived' => 'Archived',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'under_review' => 'warning',
                        'revision_required' => 'danger',
                        'approved' => 'success',
                        'final' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
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
                TextColumn::make('currentVersion.version_number')
                    ->label('Version')
                    ->placeholder('No version'),
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
            ->mapWithKeys(fn (string $status): array => [$status => self::label($status)])
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
