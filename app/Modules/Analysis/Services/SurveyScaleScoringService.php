<?php

namespace App\Modules\Analysis\Services;

use App\Models\Survey;
use Illuminate\Support\Collection;

class SurveyScaleScoringService
{
    /**
     * @param  array<int, array<string, mixed>>  $indicatorSummary
     * @return array<int, array<string, mixed>>
     */
    public function summarize(Survey $survey, array $indicatorSummary): array
    {
        $survey->loadMissing('scales');
        $indicators = collect($indicatorSummary);

        return $survey->scales
            ->map(function ($scale) use ($indicators): array {
                $scaleIndicators = $indicators
                    ->where('scale_id', $scale->getKey())
                    ->values();
                $means = $scaleIndicators->pluck('mean')->filter(fn (mixed $value): bool => is_numeric($value))->values();

                return [
                    'scale_id' => $scale->getKey(),
                    'scale_name' => $scale->name,
                    'indicator_count' => $scaleIndicators->count(),
                    'item_count' => (int) $scaleIndicators->sum('item_count'),
                    'respondent_count' => (int) $scaleIndicators->max('respondent_count'),
                    'mean' => $this->round($means->avg()),
                    'median' => $this->median($means),
                    'min' => $this->round($means->min()),
                    'max' => $this->round($means->max()),
                    'standard_deviation' => $this->standardDeviation($means),
                    'missing_count' => (int) $scaleIndicators->sum('missing_count'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $scores
     */
    private function median(Collection $scores): ?float
    {
        $numbers = $scores->filter(fn (mixed $value): bool => is_numeric($value))->map(fn (mixed $value): float => (float) $value)->sort()->values();
        $count = $numbers->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $this->round($numbers[$middle])
            : $this->round(($numbers[$middle - 1] + $numbers[$middle]) / 2);
    }

    /**
     * @param  Collection<int, mixed>  $scores
     */
    private function standardDeviation(Collection $scores): ?float
    {
        $numbers = $scores->filter(fn (mixed $value): bool => is_numeric($value))->map(fn (mixed $value): float => (float) $value)->values();

        if ($numbers->isEmpty()) {
            return null;
        }

        $mean = $numbers->avg();
        $variance = $numbers->map(fn (float $value): float => ($value - $mean) ** 2)->avg();

        return $this->round(sqrt($variance));
    }

    private function round(float|int|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 4);
    }
}
