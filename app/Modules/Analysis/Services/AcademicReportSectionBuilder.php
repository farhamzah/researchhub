<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisResult;

class AcademicReportSectionBuilder
{
    public function __construct(private readonly AcademicDraftBuilder $draftBuilder) {}

    /**
     * @return array<string, mixed>
     */
    public function build(AnalysisResult $result): array
    {
        $draft = $this->draftBuilder->build($result);
        $summary = $result->summary ?? [];

        return [
            'title' => $draft['title'],
            'subtitle' => 'Draf BAB IV - Analisis Deskriptif',
            'generated_at' => now()->toISOString(),
            'metadata' => $draft['metadata'],
            'sections' => [
                [
                    'number' => 1,
                    'heading' => 'Informasi Analisis',
                    'body' => $this->metadataText($draft['metadata']),
                ],
                [
                    'number' => 2,
                    'heading' => 'Ringkasan Sumber Data',
                    'body' => $this->sourceSummaryText($summary),
                ],
                [
                    'number' => 3,
                    'heading' => 'Ringkasan Hasil Analisis Deskriptif',
                    'body' => $draft['sections']['Ringkasan Hasil Per Pertanyaan/Indikator'] ?? 'Belum tersedia ringkasan per pertanyaan.',
                ],
                [
                    'number' => 5,
                    'heading' => 'Narasi Akademik Deskriptif',
                    'body' => $draft['sections']['Narasi Interpretasi Deskriptif'] ?? 'Draf narasi akademik belum tersedia.',
                ],
                [
                    'number' => 6,
                    'heading' => 'Catatan Interpretasi',
                    'body' => $this->interpretationNotes(),
                ],
            ],
            'tables_heading' => '4. Tabel Hasil Analisis',
            'tables' => $draft['tables'],
            'verification_checklist_heading' => '7. Checklist Verifikasi Peneliti',
            'verification_checklist' => $this->verificationChecklist(),
            'disclaimer_heading' => '8. Disclaimer',
            'disclaimer' => $draft['disclaimer'],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function metadataText(array $metadata): string
    {
        $lines = [];

        foreach ($metadata as $key => $value) {
            $lines[] = str_replace('_', ' ', $key).': '.($value ?: '-');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function sourceSummaryText(array $summary): string
    {
        return 'Dokumen ini disusun dari data respons survei yang telah tersimpan di ResearchHub. '
            .'Analisis menggunakan '.($summary['submitted_count'] ?? 0).' respons submitted dari total '
            .($summary['response_count'] ?? 0).' respons tercatat. Jumlah butir yang dianalisis adalah '
            .($summary['analyzed_question_count'] ?? 0).'. Sebanyak '
            .($summary['hidden_question_count'] ?? 0)
            .' butir hidden dikecualikan dari ekspor akademik untuk menjaga keamanan data.';
    }

    private function interpretationNotes(): string
    {
        return 'Narasi pada dokumen ini menunjukkan kecenderungan dan menggambarkan distribusi respons berdasarkan analisis deskriptif. '
            .'Setiap interpretasi perlu diverifikasi lebih lanjut oleh peneliti sebelum digunakan dalam naskah resmi. '
            .'Dokumen ini tidak memuat kesimpulan inferensial, pengujian hipotesis, atau klaim hubungan sebab-akibat.';
    }

    /**
     * @return array<int, string>
     */
    private function verificationChecklist(): array
    {
        if (! (bool) config('researchhub_analysis.docx.include_verification_checklist', true)) {
            return [];
        }

        return [
            '[ ] Data responden telah diverifikasi.',
            '[ ] Jumlah respons sesuai dengan kriteria analisis.',
            '[ ] Interpretasi deskriptif telah diperiksa oleh peneliti.',
            '[ ] Narasi telah dikonsultasikan dengan pembimbing.',
            '[ ] Kesimpulan inferensial belum ditambahkan sebelum uji statistik lanjutan.',
        ];
    }
}
