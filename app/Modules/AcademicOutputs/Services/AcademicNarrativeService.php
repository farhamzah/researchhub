<?php

namespace App\Modules\AcademicOutputs\Services;

use App\Models\AnalysisResult;
use App\Models\Document;
use App\Models\ResearchProject;
use App\Models\SupervisionFeedback;
use App\Models\SupervisionFollowUpItem;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationRound;
use App\Modules\Projects\Services\ProjectResearchJourneyService;
use App\Modules\Validation\Services\SurveyValidationResultService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AcademicNarrativeService
{
    public function __construct(
        private readonly SurveyValidationResultService $validationResults,
        private readonly ProjectResearchJourneyService $journeyService,
    ) {}

    public function surveyInstrumentSummary(Survey $survey): string
    {
        if (filled($survey->instrument_summary_override)) {
            return (string) $survey->instrument_summary_override;
        }

        $survey->loadMissing([
            'project',
            'questions.scoring.indicator',
            'indicators',
            'scales',
        ]);

        $questions = $this->visibleQuestions($survey);
        $indicators = $survey->indicators
            ->pluck('name')
            ->filter()
            ->values();
        $questionTypes = $questions
            ->pluck('type')
            ->map(fn (string $type): string => $this->label($type))
            ->unique()
            ->values();
        $scoredQuestions = $questions
            ->filter(fn (SurveyQuestion $question): bool => (bool) $question->scoring?->is_scored)
            ->count();

        if ($questions->isEmpty()) {
            return 'Instrumen survey '.$survey->title.' belum memiliki butir pertanyaan yang cukup untuk disusun sebagai ringkasan akademik. Lengkapi indikator, skala, dan butir pertanyaan sebelum digunakan untuk validasi atau pengambilan data.';
        }

        if ($survey->instrument_type === Survey::INSTRUMENT_PRACTITIONER_INTERVIEW) {
            return sprintf(
                'Pedoman Wawancara Praktisi/Ahli CPOB PharmVR merupakan instrumen kualitatif terstruktur untuk menggali akurasi konten CPOB/GMP, prioritas scene, risiko miskonsepsi, kebutuhan industri, dan rekomendasi desain PharmVR. Instrumen ini memuat %d butir pertanyaan dengan tema deskriptif %s. Instrumen ini tidak dirancang sebagai instrumen skor numerik, tetapi menggunakan indikator/tema deskriptif untuk memudahkan analisis tematik, supervisor review, dan penyusunan synthesis matrix.',
                $questions->count(),
                $this->series($indicators, 'indikator deskriptif belum tersedia'),
            );
        }

        if ($survey->instrument_type === Survey::INSTRUMENT_ANALYSIS_LECTURER) {
            return sprintf(
                'Kuesioner Analisis Kebutuhan Dosen PharmVR merupakan instrumen campuran yang memuat item Likert, pilihan prioritas, dan pertanyaan terbuka untuk menggali kebutuhan pembelajaran CPOB/GMP dari perspektif dosen. Instrumen ini memiliki %d butir pertanyaan yang terhubung dengan indikator %s. Item Likert digunakan untuk ringkasan kuantitatif deskriptif, sedangkan pilihan prioritas dan masukan terbuka digunakan sebagai bukti deskriptif untuk synthesis matrix dan arahan desain PharmVR.',
                $questions->count(),
                $this->series($indicators, 'indikator belum tersedia'),
            );
        }

        return sprintf(
            'Instrumen survey %s disusun untuk project %s dengan %d butir pertanyaan yang dapat dibaca responden. Struktur instrumen memuat tipe pertanyaan %s dan saat ini terkait dengan %d indikator%s. Sebanyak %d butir telah memiliki konfigurasi skoring. Ringkasan ini bersifat deskriptif berdasarkan konfigurasi instrumen yang tersedia, sehingga kelayakan isi tetap perlu dikonfirmasi melalui validasi ahli dan arahan pembimbing/promotor.',
            $survey->title,
            $survey->project?->title ?: 'riset yang belum diberi nama',
            $questions->count(),
            $this->series($questionTypes, 'belum diklasifikasikan'),
            $indicators->count(),
            $indicators->isEmpty() ? '' : ', yaitu '.$this->series($indicators, 'indikator belum tersedia'),
            $scoredQuestions,
        );
    }

    public function expertValidationSummary(SurveyValidationRound $round): string
    {
        $result = $this->validationResults->analyze($round);
        $summary = $result->summary;

        if ((int) $summary['assigned_count'] === 0) {
            return 'Ringkasan validasi ahli untuk '.$round->survey->title.' belum dapat disusun karena belum ada validator yang ditugaskan pada putaran ini.';
        }

        if ((int) $summary['submitted_count'] === 0) {
            return sprintf(
                'Putaran validasi %s untuk instrumen %s telah menugaskan %d validator, tetapi belum ada penilaian yang dikirimkan. Narasi akademik hasil validasi belum boleh menyimpulkan kelayakan instrumen sampai data penilaian tersedia.',
                $round->title,
                $round->survey->title,
                $summary['assigned_count'],
            );
        }

        $preliminary = $summary['is_preliminary']
            ? ' Karena belum semua validator mengirimkan penilaian, hasil ini masih bersifat sementara.'
            : '';

        return sprintf(
            'Berdasarkan putaran validasi %s, sebanyak %d dari %d validator telah mengirimkan penilaian terhadap %d butir instrumen %s. Hasil sementara menunjukkan %d butir berada pada kategori valid, %d butir memerlukan revisi, dan %d butir berada pada kategori tidak layak menurut kriteria bantu sistem.%s Interpretasi ini tidak menggantikan keputusan akademik peneliti dan pembimbing/promotor.',
            $round->title,
            $summary['submitted_count'],
            $summary['assigned_count'],
            $summary['question_count'],
            $round->survey->title,
            $summary['valid_count'],
            $summary['revise_count'],
            $summary['reject_count'],
            $preliminary,
        );
    }

    public function validityInterpretation(SurveyValidationRound $round): string
    {
        $result = $this->validationResults->analyze($round);
        $summary = $result->summary;

        if ((int) $summary['submitted_count'] === 0) {
            return 'Interpretasi Aiken/CVI belum tersedia karena belum ada penilaian validasi ahli yang dikirimkan.';
        }

        return sprintf(
            'Nilai rata-rata Aiken\'s V tercatat %s, rata-rata I-CVI %s, S-CVI/Ave %s, dan S-CVI/UA %s. Dalam pembacaan awal, nilai di sekitar atau di atas 0,80 dapat menjadi indikator dukungan validitas isi, sedangkan nilai sekitar 0,60-0,79 perlu dibaca sebagai sinyal revisi. Nilai di bawah 0,60 perlu ditinjau lebih hati-hati bersama komentar validator. Angka ini adalah alat bantu keputusan, bukan bukti tunggal kelayakan instrumen.',
            $this->metric($summary['average_aiken_v']),
            $this->metric($summary['average_i_cvi']),
            $this->metric($summary['s_cvi_ave']),
            $this->metric($summary['s_cvi_ua']),
        );
    }

    public function surveyAnalysisSummary(Survey $survey): string
    {
        $survey->loadMissing(['analysisResults']);

        $submittedCount = $survey->responses()
            ->submitted()
            ->official()
            ->count();
        $latestAnalysis = $survey->analysisResults()
            ->latest()
            ->first();

        if ($submittedCount === 0) {
            return 'Ringkasan respons survey '.$survey->title.' belum dapat disusun karena belum ada respons terkirim. Narasi analisis perlu menunggu data responden yang sah dan pemeriksaan kualitas data.';
        }

        if (! $latestAnalysis) {
            return sprintf(
                'Survey %s telah memiliki %d respons terkirim, tetapi hasil analisis terstruktur belum tersedia. Data ini baru dapat digunakan sebagai dasar narasi akademik setelah analisis deskriptif atau analisis lain yang sesuai selesai dijalankan.',
                $survey->title,
                $submittedCount,
            );
        }

        $indicatorSummary = $this->indicatorSummary($latestAnalysis);

        return sprintf(
            'Berdasarkan %d respons terkirim pada survey %s, hasil analisis %s telah tersedia sebagai ringkasan awal. %s Narasi ini hanya menggambarkan kecenderungan data yang tersimpan di sistem dan tidak memuat identitas responden. Interpretasi akhir tetap perlu disesuaikan dengan desain penelitian, ukuran sampel, dan arahan pembimbing/promotor.',
            $submittedCount,
            $survey->title,
            $latestAnalysis->title,
            $indicatorSummary,
        );
    }

    public function supervisionSummary(SupervisionSession $session): string
    {
        $session->loadMissing(['feedback', 'followUpItems', 'resources']);

        $latestFeedback = $session->feedback->sortByDesc('created_at')->first();
        $feedbackText = $latestFeedback instanceof SupervisionFeedback && filled($latestFeedback->general_feedback)
            ? 'Masukan pembimbing/promotor yang tercatat menekankan: '.$this->plain($latestFeedback->general_feedback).'.'
            : 'Belum ada masukan pembimbing/promotor yang dikirimkan pada sesi ini.';
        $resources = $session->resources
            ->map(fn ($resource): string => $resource->displayTitle())
            ->filter()
            ->take(5)
            ->values();
        $resourceText = $resources->isEmpty()
            ? 'Resource yang dibagikan belum tersedia.'
            : 'Resource yang dibagikan meliputi '.$this->series($resources, 'belum tersedia').'.';
        $followUpCount = $session->followUpItems->count();
        $openFollowUps = $session->followUpItems
            ->reject(fn (SupervisionFollowUpItem $item): bool => in_array($item->status, [SupervisionFollowUpItem::STATUS_COMPLETED, SupervisionFollowUpItem::STATUS_CANCELLED], true))
            ->count();

        return sprintf(
            'Sesi bimbingan %s berstatus %s dan membahas agenda %s. Progress yang dilaporkan adalah %s. %s %s Terdapat %d tindak lanjut yang tercatat, dengan %d item masih perlu diselesaikan. Ringkasan ini disiapkan sebagai bahan penyusunan log bimbingan dan perlu diverifikasi kembali sebelum dimasukkan ke dokumen akademik.',
            $session->title,
            SupervisionSession::STATUS_LABELS[$session->status] ?? $this->label($session->status),
            $this->plain($session->agenda ?: 'belum dituliskan secara rinci'),
            $this->plain($session->progress_report ?: 'belum tersedia'),
            $feedbackText,
            $resourceText,
            $followUpCount,
            $openFollowUps,
        );
    }

    public function followUpSummary(ResearchProject $project): string
    {
        $project->loadMissing(['supervisionSessions.followUpItems']);

        $items = $project->supervisionSessions
            ->flatMap(fn (SupervisionSession $session): Collection => $session->followUpItems)
            ->values();

        if ($items->isEmpty()) {
            return 'Belum ada tindak lanjut revisi yang tercatat untuk project '.$project->title.'. Setelah sesi bimbingan atau validasi ahli selesai, catatan revisi perlu dibuat agar perkembangan riset dapat ditelusuri.';
        }

        $completed = $items->where('status', SupervisionFollowUpItem::STATUS_COMPLETED)->count();
        $open = $items
            ->reject(fn (SupervisionFollowUpItem $item): bool => in_array($item->status, [SupervisionFollowUpItem::STATUS_COMPLETED, SupervisionFollowUpItem::STATUS_CANCELLED], true))
            ->count();
        $priority = $items
            ->whereIn('priority', [SupervisionFollowUpItem::PRIORITY_HIGH, SupervisionFollowUpItem::PRIORITY_URGENT])
            ->count();
        $nextItems = $items
            ->whereIn('status', [SupervisionFollowUpItem::STATUS_TODO, SupervisionFollowUpItem::STATUS_IN_PROGRESS, SupervisionFollowUpItem::STATUS_WAITING_SUPERVISOR])
            ->take(3)
            ->pluck('title');

        return sprintf(
            'Project %s memiliki %d tindak lanjut revisi yang tercatat dari proses bimbingan. Sebanyak %d item telah selesai, %d item masih terbuka, dan %d item memiliki prioritas tinggi atau mendesak. Fokus tindak lanjut terdekat mencakup %s. Ringkasan ini membantu peneliti menjaga kontinuitas revisi tanpa menyimpulkan bahwa seluruh masukan akademik telah terpenuhi.',
            $project->title,
            $items->count(),
            $completed,
            $open,
            $priority,
            $this->series($nextItems, 'belum ada item terbuka'),
        );
    }

    public function documentProgressSummary(ResearchProject $project): string
    {
        $project->loadMissing(['documents.category']);

        $documents = $project->documents;

        if ($documents->isEmpty()) {
            return 'Project '.$project->title.' belum memiliki dokumen akademik yang tercatat. Tambahkan proposal, bab, instrumen, atau draft artikel agar progres dokumen dapat dipantau bersama status revisinya.';
        }

        $types = $documents
            ->map(fn (Document $document): string => $document->documentTypeLabel())
            ->unique()
            ->take(6)
            ->values();
        $revisionDocuments = $documents
            ->filter(fn (Document $document): bool => $document->needsRevision() || $document->isRevisionOverdue())
            ->take(3);
        $underReviewCount = $documents->where('status', Document::STATUS_UNDER_REVIEW)->count();
        $approvedCount = $documents
            ->whereIn('status', [Document::STATUS_APPROVED, Document::STATUS_FINAL])
            ->count();
        $nextActions = $revisionDocuments
            ->pluck('next_action')
            ->filter()
            ->values();

        if ($revisionDocuments->isNotEmpty()) {
            return sprintf(
                'Dokumen project %s terdiri atas %d dokumen, termasuk %s. Saat ini %d dokumen sudah approved/final dan %d dokumen sedang under review. Perhatian utama ada pada %s, dengan tindak lanjut terdekat: %s. Ringkasan ini bersifat operasional untuk membantu prioritas revisi dokumen, bukan penilaian final kualitas akademik.',
                $project->title,
                $documents->count(),
                $this->series($types, 'dokumen belum diklasifikasikan'),
                $approvedCount,
                $underReviewCount,
                $this->series($revisionDocuments->pluck('title')->values(), 'dokumen revisi belum ditentukan'),
                $this->series($nextActions, 'lengkapi next action pada dokumen revisi'),
            );
        }

        return sprintf(
            'Dokumen project %s terdiri atas %d dokumen, termasuk %s. Sebanyak %d dokumen sudah approved/final dan %d dokumen sedang under review. Belum ada dokumen yang ditandai membutuhkan revisi atau melewati batas revisi, sehingga fokus berikutnya adalah menjaga versi current dan melanjutkan review akademik sesuai arahan pembimbing/promotor.',
            $project->title,
            $documents->count(),
            $this->series($types, 'dokumen belum diklasifikasikan'),
            $approvedCount,
            $underReviewCount,
        );
    }

    public function projectProgressSummary(ResearchProject $project): string
    {
        $journey = $this->journeyService->build($project);
        $steps = $journey['steps'];
        $attentionLabels = $steps
            ->filter(fn (array $step): bool => $step['status'] === ProjectResearchJourneyService::STATUS_NEEDS_ATTENTION)
            ->pluck('label')
            ->take(4);

        return sprintf(
            'Alur riset project %s menunjukkan progress sebesar %d%% dengan %d dari %d langkah utama telah selesai. Saat ini terdapat %d area yang perlu perhatian, terutama %s. Langkah berikutnya yang disarankan sistem adalah %s. Ringkasan ini bersifat operasional untuk membantu prioritas kerja, bukan penilaian akhir atas kualitas akademik project.',
            $project->title,
            $journey['progress_percentage'],
            $journey['completed_count'],
            $steps->count(),
            $journey['attention_count'],
            $this->series($attentionLabels, 'belum ada area prioritas khusus'),
            $journey['next_step']['label'],
        );
    }

    /**
     * @return Collection<int, SurveyQuestion>
     */
    private function visibleQuestions(Survey $survey): Collection
    {
        return $survey->questions
            ->reject(fn (SurveyQuestion $question): bool => $question->type === SurveyQuestion::TYPE_HIDDEN)
            ->values();
    }

    private function indicatorSummary(AnalysisResult $result): string
    {
        $payload = $result->result_payload ?? [];
        $summary = $result->summary ?? [];
        $indicators = collect($payload['indicator_summary'] ?? $summary['indicator_summary'] ?? [])
            ->take(4)
            ->map(function (mixed $item): string {
                if (! is_array($item)) {
                    return '';
                }

                $name = (string) ($item['indicator'] ?? $item['name'] ?? 'indikator');
                $mean = $item['mean'] ?? $item['average'] ?? null;
                $label = (string) ($item['interpretation_label'] ?? $item['interpretation'] ?? '');

                return trim($name.' '.($mean !== null ? '(rerata '.$this->metric((float) $mean).')' : '').($label !== '' ? ' dengan interpretasi '.$label : ''));
            })
            ->filter();

        if ($indicators->isEmpty()) {
            return 'Hasil analisis telah tersimpan, tetapi ringkasan indikator belum tersedia secara terstruktur.';
        }

        return 'Indikator yang terbaca dalam ringkasan meliputi '.$this->series($indicators, 'indikator belum tersedia').'.';
    }

    private function label(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function metric(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 3) : 'belum tersedia';
    }

    /**
     * @param  Collection<int, string>  $items
     */
    private function series(Collection $items, string $fallback): string
    {
        $clean = $items
            ->map(fn (string $item): string => trim($this->plain($item)))
            ->filter()
            ->values();

        if ($clean->isEmpty()) {
            return $fallback;
        }

        if ($clean->count() === 1) {
            return $clean->first();
        }

        return $clean->slice(0, -1)->join(', ').', dan '.$clean->last();
    }

    private function plain(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(500, '...')
            ->toString();
    }
}
