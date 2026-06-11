<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Documents';

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
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('visibility')
                    ->badge()
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
                    ->relationship('project', 'title'),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                SelectFilter::make('status')
                    ->options(array_combine(Document::STATUSES, Document::STATUSES)),
            ])
            ->recordActions([
                Action::make('reviewLinks')
                    ->label('Review Links')
                    ->icon('heroicon-o-link')
                    ->visible(fn (Document $record): bool => auth()->user()?->can('createReviewLink', $record) ?? false)
                    ->url(fn (Document $record): string => route('admin.documents.review-links.index', ['document' => $record])),
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
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
