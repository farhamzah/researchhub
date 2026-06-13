<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ResearchProjectResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResearchProjects extends ManageRecords
{
    protected static string $resource = ResearchProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFromTemplate')
                ->label('Buat dari Template')
                ->icon('heroicon-o-squares-plus')
                ->url(route('admin.projects.templates.index')),
            CreateAction::make()
                ->label('Create Research Project')
                ->mutateDataUsing(function (array $data): array {
                    $data['owner_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
