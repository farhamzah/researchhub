<?php

namespace App\Modules\Analysis\Services;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class AnalysisDocxStyleFactory
{
    /**
     * @return array<string, mixed>
     */
    public function sectionSettings(): array
    {
        return [
            'marginTop' => (int) config('researchhub_analysis.docx.margin_top', 1440),
            'marginRight' => (int) config('researchhub_analysis.docx.margin_right', 1080),
            'marginBottom' => (int) config('researchhub_analysis.docx.margin_bottom', 1440),
            'marginLeft' => (int) config('researchhub_analysis.docx.margin_left', 1080),
        ];
    }

    public function configure(PhpWord $phpWord): void
    {
        $phpWord->setDefaultFontName((string) config('researchhub_analysis.docx.default_font', 'Calibri'));
        $phpWord->setDefaultFontSize((int) config('researchhub_analysis.docx.default_font_size', 11));

        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 18], ['spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => (int) config('researchhub_analysis.docx.heading_font_size', 14)], [
            'spaceBefore' => 240,
            'spaceAfter' => 120,
        ]);
        $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 12], ['spaceBefore' => 160, 'spaceAfter' => 80]);

        $phpWord->addTableStyle('ResearchHubAnalysisTable', [
            'borderSize' => 6,
            'borderColor' => 'B8C0CC',
            'cellMargin' => 80,
        ], [
            'bgColor' => 'E8F3EF',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParagraph(): array
    {
        return [
            'alignment' => Jc::BOTH,
            'spaceAfter' => 120,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mutedText(): array
    {
        return [
            'color' => '5B6472',
            'size' => 10,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tableText(): array
    {
        return [
            'size' => (int) config('researchhub_analysis.docx.table_font_size', 9),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tableHeaderText(): array
    {
        return [
            'bold' => true,
            'size' => (int) config('researchhub_analysis.docx.table_font_size', 9),
        ];
    }
}
