<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisResult;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

class AnalysisDocxExporter
{
    public function __construct(
        private readonly AcademicReportSectionBuilder $sectionBuilder,
        private readonly AnalysisDocxStyleFactory $styleFactory,
    ) {}

    public function export(AnalysisResult $result): string
    {
        $report = $this->sectionBuilder->build($result);
        $phpWord = new PhpWord;
        $this->styleFactory->configure($phpWord);

        $section = $phpWord->addSection($this->styleFactory->sectionSettings());
        $this->addFooter($section);
        $this->addTitleBlock($section, $report);

        $tablesAdded = false;
        foreach ($report['sections'] as $sectionData) {
            $section->addTitle($sectionData['number'].'. '.$sectionData['heading'], 2);
            $this->addParagraphs($section, (string) $sectionData['body']);

            if ($sectionData['number'] === 3) {
                $section->addTitle($report['tables_heading'], 2);
                $this->addTables($section, $report['tables']);
                $tablesAdded = true;
            }
        }

        if (! $tablesAdded) {
            $section->addTitle($report['tables_heading'], 2);
            $this->addTables($section, $report['tables']);
        }

        if ($report['verification_checklist'] !== []) {
            $section->addTitle($report['verification_checklist_heading'], 2);
            foreach ($report['verification_checklist'] as $item) {
                $section->addText($item);
            }
        }

        $section->addTitle($report['disclaimer_heading'], 2);
        $section->addText($report['disclaimer'], ['italic' => true], $this->styleFactory->bodyParagraph());

        $docxPath = $this->temporaryDocxPath();

        IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);
        $content = file_get_contents($docxPath);
        unlink($docxPath);

        if ($content === false) {
            throw new RuntimeException('Unable to read generated analysis DOCX.');
        }

        return $content;
    }

    public function filename(AnalysisResult $result): string
    {
        return 'analysis-draft-'.$this->safeIdentifier($result->getKey()).'-'.now()->format('Ymd').'.docx';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function addTitleBlock(Section $section, array $report): void
    {
        $section->addTitle($report['title'], 1);
        $section->addText($report['subtitle'], ['bold' => true, 'size' => 12, 'color' => '166A54']);
        $section->addText('Status: draf akademik otomatis berbasis analisis deskriptif.', $this->styleFactory->mutedText());
        $section->addText('generated at: '.$report['generated_at'], $this->styleFactory->mutedText());
        $section->addTextBreak();
    }

    private function addFooter(Section $section): void
    {
        if (! (bool) config('researchhub_analysis.docx.include_footer', true)) {
            return;
        }

        $section->addFooter()->addText('ResearchHub - Draf otomatis deskriptif, wajib diverifikasi.', $this->styleFactory->mutedText());
    }

    private function addParagraphs(Section $section, string $content): void
    {
        foreach (explode("\n", $content) as $line) {
            $section->addText($line === '' ? ' ' : $line, [], $this->styleFactory->bodyParagraph());
        }
    }

    private function addTables(Section $section, mixed $tables): void
    {
        foreach ($tables as $table) {
            $columns = $this->readableColumns($table->columns);
            $section->addTitle($table->title, 3);

            if ($columns !== $table->columns) {
                $section->addText('Kolom tabel disederhanakan untuk menjaga keterbacaan dokumen DOCX.', $this->styleFactory->mutedText());
            }

            $wordTable = $section->addTable('ResearchHubAnalysisTable');
            $cellWidth = (int) floor(9000 / max(1, count($columns)));

            $wordTable->addRow();
            foreach ($columns as $column) {
                $wordTable->addCell($cellWidth)->addText(str_replace('_', ' ', (string) $column), $this->styleFactory->tableHeaderText());
            }

            foreach ($table->rows as $row) {
                $wordTable->addRow();
                foreach ($columns as $column) {
                    $wordTable->addCell($cellWidth)->addText($this->stringValue($row[$column] ?? '-'), $this->styleFactory->tableText());
                }
            }

            $section->addTextBreak();
        }
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function readableColumns(array $columns): array
    {
        if (count($columns) <= 6) {
            return $columns;
        }

        $preferred = [
            'question_key',
            'type',
            'question_type',
            'metric',
            'value',
            'percentage',
            'answered_count',
            'missing_count',
            'mean',
        ];

        $readable = array_values(array_intersect($preferred, $columns));

        return array_slice($readable === [] ? $columns : $readable, 0, 6);
    }

    private function temporaryDocxPath(): string
    {
        $path = tempnam(storage_path('framework/cache'), 'analysis-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary analysis DOCX path.');
        }

        $docxPath = $path.'.docx';
        rename($path, $docxPath);

        return $docxPath;
    }

    private function safeIdentifier(string $identifier): string
    {
        return preg_replace('/[^A-Za-z0-9-]/', '-', $identifier) ?: 'analysis';
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
}
