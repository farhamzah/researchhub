<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Modules\Documents\Actions\CreateDocumentAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDocuments extends ManageRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Document Record')
                ->using(fn (array $data): Document => app(CreateDocumentAction::class)->handle(
                    auth()->user(),
                    DocumentResource::managedProjectFromForm($data),
                    DocumentResource::categoryFromForm($data),
                    $data,
                )),
        ];
    }
}
