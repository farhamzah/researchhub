<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisResult;

class AcademicDraftBuilder
{
    public const DISCLAIMER = 'Dokumen ini merupakan draf akademik otomatis berbasis analisis deskriptif. Interpretasi akhir perlu diverifikasi oleh peneliti dan pembimbing sebelum digunakan dalam naskah resmi.';

    /**
     * @return array<string, mixed>
     */
    public function build(AnalysisResult $result): array
    {
        $result->loadMissing(['job', 'survey', 'tables', 'narratives']);

        return [
            'title' => 'Draf Akademik - '.$result->title,
            'metadata' => [
                'analysis_result_id' => $result->getKey(),
                'analysis_job_id' => $result->analysis_job_id,
                'analysis_type' => $result->type,
                'survey_title' => $result->survey?->title,
                'created_at' => $result->created_at?->toISOString(),
            ],
            'sections' => [
                'Judul Analisis' => $result->title,
                'Ringkasan Data' => $this->summaryText($result),
                'Deskripsi Responden tanpa identitas' => 'Data responden disajikan dalam bentuk agregat tanpa nama, email, nomor identitas, atau atribut personal lain. Ringkasan ini hanya menggunakan respons survei yang berstatus submitted.',
                'Ringkasan Hasil Per Pertanyaan/Indikator' => $this->questionSummaryText($result),
                'Narasi Interpretasi Deskriptif' => $this->narrativeText($result),
                'Catatan Verifikasi' => self::DISCLAIMER,
            ],
            'tables' => $result->tables,
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    private function summaryText(AnalysisResult $result): string
    {
        $summary = $result->summary ?? [];

        return 'Analisis deskriptif ini menggunakan '
            .($summary['submitted_count'] ?? 0)
            .' respons submitted dari total '
            .($summary['response_count'] ?? 0)
            .' respons yang tercatat. Jumlah butir yang dianalisis adalah '
            .($summary['analyzed_question_count'] ?? 0)
            .', sedangkan '
            .($summary['hidden_question_count'] ?? 0)
            .' butir hidden dikecualikan dari narasi dan ekspor akademik.';
    }

    private function questionSummaryText(AnalysisResult $result): string
    {
        $questions = collect($result->result_payload['questions'] ?? []);

        if ($questions->isEmpty()) {
            return 'Belum tersedia ringkasan per pertanyaan.';
        }

        return $questions
            ->map(fn (array $question): string => '- '.$question['label'].' ('.$question['question_key'].', '.$question['type'].'): answered '
                .$question['answered_count'].', missing '.$question['missing_count'])
            ->implode("\n");
    }

    private function narrativeText(AnalysisResult $result): string
    {
        return $result->narratives
            ->where('language', 'id')
            ->pluck('narrative')
            ->implode("\n\n") ?: 'Draf narasi akademik belum tersedia.';
    }
}
