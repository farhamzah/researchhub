<?php

namespace App\Filament\Resources\ExpertValidators;

use App\Filament\Resources\ExpertValidators\Pages\ManageExpertValidators;
use App\Models\ExpertValidator;
use App\Modules\Validation\Actions\DeleteExpertValidatorAction;
use App\Modules\Validation\Actions\UpdateExpertValidatorAction;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class ExpertValidatorResource extends Resource
{
    protected static ?string $model = ExpertValidator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Instrumen & Survei';

    protected static ?string $navigationLabel = 'Validator Ahli';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Nomor telepon')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('institution')
                    ->label('Institusi')
                    ->maxLength(255),
                TextInput::make('position')
                    ->label('Jabatan')
                    ->maxLength(255),
                TagsInput::make('expertise_areas')
                    ->label('Bidang keahlian')
                    ->placeholder('Tambahkan bidang keahlian')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Catatan internal')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Toggle::make('is_global')
                    ->label('Validator global yang bisa dipakai ulang')
                    ->default(false)
                    ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada validator ahli')
            ->emptyStateDescription('Tambahkan profil validator ahli agar bisa dipilih saat menugaskan validasi instrumen atau proyek riset.')
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Validator')
                    ->description(fn (ExpertValidator $record): string => collect([$record->position, $record->institution])->filter()->join(' - ') ?: 'Afiliasi belum dicatat')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('expertise_areas')
                    ->label('Keahlian')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->placeholder('Belum diisi'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_global')
                    ->label('Global')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('assignments_count')
                    ->label('Proyek')
                    ->counts('assignments')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Aktif')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Tidak aktif',
                    ]),
                SelectFilter::make('is_global')
                    ->label('Global')
                    ->options([
                        '1' => 'Global',
                        '0' => 'Pribadi',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (ExpertValidator $record, array $data): ExpertValidator => app(UpdateExpertValidatorAction::class)->handle(auth()->user(), $record, $data))
                    ->visible(fn (ExpertValidator $record): bool => auth()->user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->using(function (ExpertValidator $record): void {
                        app(DeleteExpertValidatorAction::class)->handle(auth()->user(), $record);
                    })
                    ->visible(fn (ExpertValidator $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpertValidators::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        if (! auth()->check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with('creator')
            ->visibleTo(auth()->user())
            ->orderByDesc('is_global')
            ->orderBy('name');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', ExpertValidator::class) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}
