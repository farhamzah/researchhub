<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisResult;

class AnalysisMarkdownExporter
{
    public function __construct(private readonly AcademicDraftBuilder $draftBuilder) {}

    public function export(AnalysisResult $result): string
    {
        $draft = $this->draftBuilder->build($result);
        $lines = [
            '# '.$draft['title'],
            '',
            '## Analysis Metadata',
        ];

        foreach ($draft['metadata'] as $key => $value) {
            $lines[] = '- **'.str_replace('_', ' ', $key).':** '.($value ?: '-');
        }

        foreach ($draft['sections'] as $heading => $content) {
            $lines[] = '';
            $lines[] = '## '.$heading;
            $lines[] = '';
            $lines[] = (string) $content;
        }

        foreach ($draft['tables'] as $table) {
            $lines[] = '';
            $lines[] = '## '.$table->title;
            $lines[] = '';
            array_push($lines, ...$this->markdownTable($table->columns, $table->rows));
        }

        $lines[] = '';
        $lines[] = '## Disclaimer';
        $lines[] = '';
        $lines[] = $draft['disclaimer'];

        return implode("\n", $lines)."\n";
    }

    public function filename(AnalysisResult $result): string
    {
        return 'analysis-draft-'.$this->safeIdentifier($result->getKey()).'-'.now()->format('Ymd').'.md';
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function markdownTable(array $columns, array $rows): array
    {
        if ($columns === []) {
            return ['No table columns available.'];
        }

        $lines = [
            '| '.implode(' | ', array_map(fn (string $column): string => $this->escape($column), $columns)).' |',
            '| '.implode(' | ', array_fill(0, count($columns), '---')).' |',
        ];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(
                fn (string $column): string => $this->escape($this->stringValue($row[$column] ?? '-')),
                $columns,
            )).' |';
        }

        return $lines;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return (string) $value;
    }

    private function escape(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }

    private function safeIdentifier(string $identifier): string
    {
        return preg_replace('/[^A-Za-z0-9-]/', '-', $identifier) ?: 'analysis';
    }
}
