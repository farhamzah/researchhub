<?php

namespace App\Filament\Resources\ExpertValidators\Pages;

use App\Filament\Resources\ExpertValidators\ExpertValidatorResource;
use App\Models\ExpertValidator;
use App\Modules\Validation\Actions\CreateExpertValidatorAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpertValidators extends ManageRecords
{
    protected static string $resource = ExpertValidatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Expert Validator')
                ->using(fn (array $data): ExpertValidator => app(CreateExpertValidatorAction::class)->handle(auth()->user(), $data)),
        ];
    }
}
