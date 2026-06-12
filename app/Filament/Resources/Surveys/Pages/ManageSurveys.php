<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Filament\Resources\Surveys\SurveyResource;
use App\Models\Survey;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageSurveys extends ManageRecords
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Survey')
                ->mutateDataUsing(function (array $data): array {
                    $data = SurveyResource::validatedProjectScopedData($data);
                    $data['created_by'] = auth()->id();
                    $data['slug'] = $this->uniqueSlug((string) $data['title']);

                    return $data;
                }),
        ];
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'survey';
        $slug = $base;
        $counter = 2;

        while (Survey::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
