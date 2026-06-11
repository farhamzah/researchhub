<?php

namespace App\Modules\Analysis\Services;

class AcademicNarrativeGenerator
{
    /**
     * @param  array<string, mixed>  $analysis
     */
    public function generate(array $analysis): string
    {
        $summary = $analysis['summary'];
        $questions = collect($analysis['questions']);
        $notable = $questions
            ->filter(fn (array $question): bool => in_array($question['type'], ['single_choice', 'multiple_choice', 'likert', 'number'], true))
            ->take(3)
            ->map(fn (array $question): string => $this->questionSentence($question))
            ->filter()
            ->implode(' ');

        $body = trim($notable) !== ''
            ? $notable
            : 'Sebagian besar butir telah diringkas pada tingkat deskriptif untuk membantu peneliti membaca pola awal data.';
        $indicatorBody = $this->indicatorSentence($analysis['indicator_summary'] ?? []);

        return 'Draf narasi akademik: Berdasarkan hasil pengisian survei, diperoleh sebanyak '
            .$summary['submitted_count']
            .' respons yang memenuhi kriteria analisis deskriptif dari total '
            .$summary['response_count']
            .' respons yang tercatat. '
            .$body
            .' '
            .$indicatorBody
            .' Ringkasan ini menunjukkan kecenderungan awal data pada tingkat deskriptif, sehingga interpretasi akhir tetap perlu diverifikasi oleh peneliti dan pembimbing sesuai konteks penelitian serta kualitas instrumen.';
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function questionSentence(array $question): string
    {
        return match ($question['type']) {
            'single_choice', 'multiple_choice' => $this->choiceSentence($question),
            'likert' => $this->likertSentence($question),
            'number' => $this->numberSentence($question),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function choiceSentence(array $question): string
    {
        $top = collect($question['frequencies'] ?? [])
            ->sortByDesc('count')
            ->first();

        if (! $top || (int) $top['count'] === 0) {
            return 'Pada butir '.$question['label'].', belum terdapat pola pilihan yang menonjol karena data jawaban masih terbatas.';
        }

        return 'Pada butir '.$question['label'].', pilihan yang paling banyak muncul adalah '
            .$top['value']
            .' dengan proporsi '
            .$top['percentage']
            .' persen dari respons yang dianalisis.';
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function likertSentence(array $question): string
    {
        if (($question['mean'] ?? null) === null) {
            return 'Pada indikator '.$question['label'].', data skala Likert belum cukup untuk diringkas secara numerik.';
        }

        return 'Pada indikator '.$question['label'].', nilai rerata sebesar '
            .$question['mean']
            .' menggambarkan kecenderungan respons pada skala yang digunakan.';
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function numberSentence(array $question): string
    {
        if (($question['mean'] ?? null) === null) {
            return 'Pada butir numerik '.$question['label'].', belum tersedia jawaban numerik yang dapat diringkas.';
        }

        return 'Pada butir numerik '.$question['label'].', rerata jawaban sebesar '
            .$question['mean']
            .' dengan rentang nilai '
            .$question['min']
            .' sampai '
            .$question['max']
            .'.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $indicatorSummary
     */
    private function indicatorSentence(array $indicatorSummary): string
    {
        $indicator = collect($indicatorSummary)
            ->filter(fn (array $summary): bool => ($summary['mean'] ?? null) !== null)
            ->first();

        if (! $indicator) {
            return '';
        }

        $label = $indicator['interpretation_label'] ?? null;
        $category = $label ? ' dan berada pada kategori '.$label : '';

        return 'Pada indikator '.$indicator['indicator_name'].', nilai rerata sebesar '
            .$indicator['mean']
            .$category
            .' berdasarkan konfigurasi skor yang tersedia; interpretasi ini masih bersifat deskriptif dan perlu diverifikasi oleh peneliti.';
    }
}
