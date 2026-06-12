<?php

namespace App\Filament\Resources\ResearchLinks\Pages;

use App\Filament\Resources\ResearchLinks\ResearchLinkResource;
use App\Models\ResearchLink;
use App\Modules\ResearchLinks\Actions\CreateResearchLinkAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResearchLinks extends ManageRecords
{
    protected static string $resource = ResearchLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Research Link')
                ->using(fn (array $data): ResearchLink => app(CreateResearchLinkAction::class)->handle(auth()->user(), $data)),
        ];
    }
}
