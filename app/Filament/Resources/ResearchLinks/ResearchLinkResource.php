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

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen & Referensi';

    protected static ?string $navigationLabel = 'Tautan Riset';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('research_project_id')
                    ->label('Proyek riset')
                    ->options(fn (): array => self::manageableProjectOptions())
                    ->searchable()
                    ->placeholder('Link global'),
                TextInput::make('title')
                    ->label('Nama situs atau referensi')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->url()
                    ->maxLength(2048),
                Select::make('category')
                    ->label('Kategori')
                    ->options(self::categoryOptions())
                    ->default(ResearchLink::CATEGORY_OTHER)
                    ->required()
                    ->in(ResearchLink::CATEGORIES),
                Textarea::make('description')
                    ->label('Deskripsi singkat')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                TextInput::make('thumbnail_url')
                    ->label('URL thumbnail')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('favicon_url')
                    ->label('URL favicon')
                    ->url()
                    ->maxLength(2048),
                Toggle::make('is_pinned')
                    ->label('Sematkan')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada link riset')
            ->emptyStateDescription('Simpan jurnal, OJS, regulasi, dataset, repositori, metodologi, dan sumber belajar yang sering dipakai.')
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('preview')
                    ->label('Pratinjau')
                    ->state(fn (ResearchLink $record): ?string => $record->thumbnail_url ?: $record->favicon_url)
                    ->square()
                    ->size(40),
                TextColumn::make('title')
                    ->label('Referensi')
                    ->description(fn (ResearchLink $record): string => $record->description ? str($record->description)->limit(90)->toString() : 'Belum ada deskripsi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::categoryOptions()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
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
                    ->label('Proyek')
                    ->placeholder('Global')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_pinned')
                    ->label('Tersemat')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
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
                    ->label('Proyek')
                    ->options(fn (): array => self::visibleProjectOptions()),
                SelectFilter::make('category')
                    ->options(self::categoryOptions()),
                SelectFilter::make('is_pinned')
                    ->label('Tersemat')
                    ->options([
                        '1' => 'Tersemat',
                        '0' => 'Tidak tersemat',
                    ]),
                SelectFilter::make('is_active')
                    ->label('Aktif')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Tidak aktif',
                    ]),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Buka Link')
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

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return [
            ResearchLink::CATEGORY_JOURNAL => 'Jurnal',
            ResearchLink::CATEGORY_CONFERENCE => 'Konferensi',
            ResearchLink::CATEGORY_REGULATION => 'Regulasi',
            ResearchLink::CATEGORY_REFERENCE => 'Referensi',
            ResearchLink::CATEGORY_DATASET => 'Dataset',
            ResearchLink::CATEGORY_REPOSITORY => 'Repositori',
            ResearchLink::CATEGORY_GOOGLE_DRIVE => 'Google Drive',
            ResearchLink::CATEGORY_OJS => 'OJS',
            ResearchLink::CATEGORY_ETHICS => 'Etik',
            ResearchLink::CATEGORY_STATISTICS => 'Statistik',
            ResearchLink::CATEGORY_LEARNING_RESOURCE => 'Sumber Belajar',
            ResearchLink::CATEGORY_METHODOLOGY => 'Metodologi',
            ResearchLink::CATEGORY_AI_TOOL => 'AI Tool',
            ResearchLink::CATEGORY_OTHER => 'Lainnya',
        ];
    }
}
