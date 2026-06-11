<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisResult;

class AnalysisCsvExporter
{
    private const HEADERS = [
        'analysis_result_id',
        'table_key',
        'table_title',
        'row_number',
        'metric',
        'value',
        'percentage',
        'question_key',
        'question_type',
    ];

    public function export(AnalysisResult $result): string
    {
        $result->loadMissing('tables');

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::HEADERS);

        foreach ($result->tables as $table) {
            foreach ($table->rows as $index => $row) {
                $normalizedRows = $this->normalizeRow($row);

                foreach ($normalizedRows as $normalizedRow) {
                    fputcsv($handle, [
                        $result->getKey(),
                        $table->table_key,
                        $table->title,
                        $index + 1,
                        $normalizedRow['metric'],
                        $normalizedRow['value'],
                        $normalizedRow['percentage'],
                        $row['question_key'] ?? null,
                        $row['type'] ?? $row['question_type'] ?? null,
                    ]);
                }
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function filename(AnalysisResult $result): string
    {
        return 'analysis-'.$result->getKey().'-tables.csv';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array{metric: string, value: string|null, percentage: string|null}>
     */
    private function normalizeRow(array $row): array
    {
        if (array_key_exists('value', $row) || array_key_exists('percentage', $row)) {
            return [[
                'metric' => (string) ($row['metric'] ?? $row['label'] ?? $row['question_key'] ?? 'value'),
                'value' => $this->stringValue($row['value'] ?? $row['count'] ?? null),
                'percentage' => $this->stringValue($row['percentage'] ?? null),
            ]];
        }

        return collect($row)
            ->reject(fn (mixed $value, string $key): bool => in_array($key, ['question_key', 'type', 'question_type'], true))
            ->map(fn (mixed $value, string $key): array => [
                'metric' => $key,
                'value' => $this->stringValue($value),
                'percentage' => null,
            ])
            ->values()
            ->all();
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return (string) $value;
    }
}
