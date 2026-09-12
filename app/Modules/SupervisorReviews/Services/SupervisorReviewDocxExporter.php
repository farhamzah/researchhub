<?php

namespace App\Modules\SupervisorReviews\Services;

use App\Models\SurveySupervisorReviewRound;
use App\Modules\Analysis\Services\AnalysisDocxStyleFactory;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

class SupervisorReviewDocxExporter
{
    public function __construct(
        private readonly SupervisorReviewReportService $reports,
        private readonly AnalysisDocxStyleFactory $styles,
    ) {}

    public function export(SurveySupervisorReviewRound $round): string
    {
        $report = $this->reports->build($round);
        $word = new PhpWord;
        $this->styles->configure($word);
        $section = $word->addSection($this->styles->sectionSettings());
        $section->addTitle($report['title'], 1);
        $section->addText('Instrumen: '.$report['instrument']);
        $section->addText('Versi: '.($report['version'] ?: '—'));
        $section->addText('Putaran: '.$report['round']);

        foreach ($report['reviewers'] as $reviewer) {
            $section->addTitle('Reviewer: '.$reviewer['name'], 2);
            $section->addText($reviewer['narrative'], [], $this->styles->bodyParagraph());
            $table = $section->addTable('ResearchHubAnalysisTable');
            $headers = ['Kode', 'Redaksi saat direview', 'Opsi jawaban', 'Keputusan', 'Komentar', 'Redaksi revisi'];
            $keys = ['code', 'reviewed_wording', 'answer_options', 'decision', 'comment', 'revised_wording'];
            $table->addRow();
            foreach ($headers as $header) {
                $table->addCell(1500)->addText($header, $this->styles->tableHeaderText());
            }
            foreach ($reviewer['rows'] as $row) {
                $table->addRow();
                foreach ($keys as $key) {
                    $table->addCell(1500)->addText((string) $row[$key], $this->styles->tableText());
                }
            }
            $section->addTextBreak();
        }

        $path = tempnam(storage_path('framework/cache'), 'supervisor-review-');
        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file DOCX sementara.');
        }
        $docxPath = $path.'.docx';
        rename($path, $docxPath);
        IOFactory::createWriter($word, 'Word2007')->save($docxPath);
        $contents = file_get_contents($docxPath);
        unlink($docxPath);
        if ($contents === false) {
            throw new RuntimeException('Tidak dapat membaca file DOCX hasil ekspor.');
        }

        return $contents;
    }

    public function filename(SurveySupervisorReviewRound $round): string
    {
        return 'laporan-review-pembimbing-'.$round->survey->instrument_identifier.'-'.now()->format('Ymd').'.docx';
    }
}
