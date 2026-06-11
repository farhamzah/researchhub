<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisResult;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class AnalysisDocxExporter
{
    public function __construct(private readonly AcademicDraftBuilder $draftBuilder) {}

    public function export(AnalysisResult $result): string
    {
        $draft = $this->draftBuilder->build($result);
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 18], ['spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 14], ['spaceBefore' => 240, 'spaceAfter' => 120]);

        $section = $phpWord->addSection();
        $section->addTitle($draft['title'], 1);

        $section->addTitle('Analysis Metadata', 2);
        foreach ($draft['metadata'] as $key => $value) {
            $section->addText(str_replace('_', ' ', $key).': '.($value ?: '-'));
        }
        $section->addText('generated at: '.now()->toISOString());

        $section->addTitle('Draft Disclaimer', 2);
        $section->addText($draft['disclaimer'], ['italic' => true], ['alignment' => Jc::BOTH]);

        foreach ($draft['sections'] as $heading => $content) {
            $section->addTitle($this->sectionHeading((string) $heading), 2);
            foreach (explode("\n", (string) $content) as $line) {
                $section->addText($line === '' ? ' ' : $line, [], ['alignment' => Jc::BOTH]);
            }
        }

        $section->addTitle('Tabel Hasil Analisis', 2);
        foreach ($draft['tables'] as $table) {
            $section->addText($table->title, ['bold' => true]);
            $wordTable = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '999999',
                'cellMargin' => 80,
            ]);

            $wordTable->addRow();
            foreach ($table->columns as $column) {
                $wordTable->addCell(2400)->addText(str_replace('_', ' ', (string) $column), ['bold' => true]);
            }

            foreach ($table->rows as $row) {
                $wordTable->addRow();
                foreach ($table->columns as $column) {
                    $wordTable->addCell(2400)->addText($this->stringValue($row[$column] ?? '-'));
                }
            }

            $section->addTextBreak();
        }

        $path = tempnam(storage_path('framework/cache'), 'analysis-docx-');
        $docxPath = $path.'.docx';
        rename($path, $docxPath);

        IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);
        $content = file_get_contents($docxPath);
        unlink($docxPath);

        return $content;
    }

    public function filename(AnalysisResult $result): string
    {
        return 'analysis-'.$result->getKey().'-academic-draft.docx';
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

    private function sectionHeading(string $heading): string
    {
        if ($heading === 'Narasi Interpretasi Deskriptif') {
            return 'Narasi Akademik Deskriptif';
        }

        return $heading;
    }
}
