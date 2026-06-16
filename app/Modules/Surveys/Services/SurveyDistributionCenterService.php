<?php

namespace App\Modules\Surveys\Services;

use App\Models\AnalysisSynthesisItem;
use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityRound;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SurveyDistributionCenterService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey, User $user): array
    {
        $survey->load([
            'project',
            'questions',
            'responses',
            'analysisResults',
            'synthesisItems',
            'validationRounds.assignments.validator',
            'readabilityRounds.participants.response',
            'distributionBatches',
        ]);

        $instruments = $this->analysisInstruments($survey);
        $batches = $survey->distributionBatches->keyBy('audience_type');
        $validationRounds = $survey->validationRounds->sortByDesc('created_at')->values();
        $readabilityRounds = $survey->readabilityRounds->sortByDesc('created_at')->values();

        return [
            'overview' => $this->overview($survey, $instruments, $validationRounds, $readabilityRounds),
            'instruments' => $this->instrumentPanels($survey, $instruments, $user, $batches),
            'validation' => $this->validationPanel($survey, $validationRounds, $user, $batches),
            'readability' => $this->readabilityPanel($survey, $readabilityRounds, $user, $batches),
            'supervisor' => $this->supervisorPanel($survey, $instruments, $validationRounds, $readabilityRounds, $user, $batches),
            'batches' => $this->batchPanels($batches),
            'statuses' => SurveyDistributionBatch::STATUS_LABELS,
            'audienceLabels' => SurveyDistributionBatch::AUDIENCE_LABELS,
            'tokenSafetyNotice' => 'Link hanya dapat ditampilkan saat dibuat atau diregenerasi. Regenerate link untuk menyalin tautan baru.',
        ];
    }

    /**
     * @return array<string, Survey|null>
     */
    private function analysisInstruments(Survey $survey): array
    {
        $groupKey = $survey->analysis_group_key ?: Survey::ANALYSIS_GROUP_PHARMVR_ADDIE;
        $related = Survey::query()
            ->withCount(['responses' => fn ($query) => $query->official()])
            ->where('project_id', $survey->project_id)
            ->where(function ($query) use ($survey, $groupKey): void {
                $query->where('id', $survey->getKey())
                    ->orWhere('parent_survey_id', $survey->getKey())
                    ->orWhere('analysis_group_key', $groupKey);
            })
            ->with(['questions', 'responses'])
            ->get();

        return [
            SurveyDistributionBatch::AUDIENCE_STUDENT => $related->firstWhere('id', $survey->getKey()) ?: $survey,
            SurveyDistributionBatch::AUDIENCE_LECTURER => $related->firstWhere('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER),
            SurveyDistributionBatch::AUDIENCE_PRACTITIONER => $related->firstWhere('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW),
        ];
    }

    /**
     * @param  array<string, Survey|null>  $instruments
     * @param  Collection<int, SurveyValidationRound>  $validationRounds
     * @param  Collection<int, SurveyReadabilityRound>  $readabilityRounds
     * @return array<int, array<string, mixed>>
     */
    private function overview(Survey $survey, array $instruments, Collection $validationRounds, Collection $readabilityRounds): array
    {
        $validationAssignments = $validationRounds->flatMap->assignments;
        $readabilityParticipants = $readabilityRounds->flatMap->participants;

        return [
            $this->instrumentOverview('Student Questionnaire', $instruments[SurveyDistributionBatch::AUDIENCE_STUDENT]),
            $this->instrumentOverview('Lecturer Questionnaire', $instruments[SurveyDistributionBatch::AUDIENCE_LECTURER]),
            $this->instrumentOverview('Practitioner Interview Form', $instruments[SurveyDistributionBatch::AUDIENCE_PRACTITIONER]),
            [
                'label' => 'Expert Validation',
                'status' => $validationAssignments->isNotEmpty() ? 'ready' : 'missing',
                'value' => $validationAssignments->where('status', SurveyValidationAssignment::STATUS_SUBMITTED)->count().' / '.$validationAssignments->count().' submitted',
            ],
            [
                'label' => 'Readability Test',
                'status' => $readabilityParticipants->isNotEmpty() ? 'ready' : 'missing',
                'value' => $readabilityParticipants->where('status', SurveyReadabilityParticipant::STATUS_SUBMITTED)->count().' / '.$readabilityParticipants->count().' submitted',
            ],
            [
                'label' => 'Analysis Report',
                'status' => $survey->analysisResults->isNotEmpty() ? 'ready' : 'missing',
                'value' => $survey->analysisResults->isNotEmpty() ? 'Available' : 'Not available',
            ],
            [
                'label' => 'Synthesis Items',
                'status' => $survey->synthesisItems->isNotEmpty() ? 'ready' : 'missing',
                'value' => $survey->synthesisItems->where('status', AnalysisSynthesisItem::STATUS_ACCEPTED)->count().' / '.$survey->synthesisItems->count().' accepted',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instrumentOverview(string $label, ?Survey $survey): array
    {
        return [
            'label' => $label,
            'status' => $survey?->canReceiveResponses() ? 'ready' : ($survey ? 'draft' : 'missing'),
            'value' => $survey ? $this->officialResponses($survey).' responses' : 'Missing',
        ];
    }

    /**
     * @param  array<string, Survey|null>  $instruments
     * @param  Collection<string, SurveyDistributionBatch>  $batches
     * @return array<int, array<string, mixed>>
     */
    private function instrumentPanels(Survey $mainSurvey, array $instruments, User $user, Collection $batches): array
    {
        $configs = [
            SurveyDistributionBatch::AUDIENCE_STUDENT => [
                'label' => 'Mahasiswa',
                'missing_cta' => null,
                'whatsapp' => 'Yth. Mahasiswa/i, saya sedang melakukan penelitian pengembangan PharmVR, yaitu media pembelajaran Virtual Reality berbasis CPOB/GMP untuk pendidikan farmasi industri. Mohon kesediaannya mengisi kuesioner analisis kebutuhan melalui link berikut: {link}. Waktu pengisian sekitar {duration}. Tidak ada jawaban benar/salah, dan data akan dijaga kerahasiaannya. Terima kasih.',
                'email' => "Yth. Mahasiswa/i,\n\nSaya {researcher} sedang melakukan penelitian pengembangan PharmVR, media pembelajaran Virtual Reality berbasis CPOB/GMP untuk pendidikan farmasi industri.\n\nMohon kesediaannya mengisi kuesioner analisis kebutuhan berikut:\n{link}\n\nWaktu pengisian sekitar {duration}. Tidak ada jawaban benar/salah, dan data akan dijaga kerahasiaannya.\n\nTerima kasih.",
            ],
            SurveyDistributionBatch::AUDIENCE_LECTURER => [
                'label' => 'Dosen',
                'missing_cta' => 'Create Lecturer Questionnaire',
                'create_route' => route('admin.surveys.analysis.create-lecturer-questionnaire', ['survey' => $mainSurvey]),
                'whatsapp' => 'Yth. Bapak/Ibu Dosen, saya sedang melakukan penelitian pengembangan PharmVR sebagai media pembelajaran CPOB/GMP berbasis Virtual Reality. Mohon kesediaan Bapak/Ibu mengisi kuesioner analisis kebutuhan dosen melalui link berikut: {link}. Masukan Bapak/Ibu akan menjadi dasar pengembangan konten, assessment, dan desain pembelajaran PharmVR. Terima kasih.',
                'email' => "Yth. Bapak/Ibu Dosen,\n\nSaya {researcher} sedang melakukan penelitian pengembangan PharmVR sebagai media pembelajaran CPOB/GMP berbasis Virtual Reality.\n\nMohon kesediaan Bapak/Ibu mengisi kuesioner analisis kebutuhan dosen melalui link berikut:\n{link}\n\nMasukan Bapak/Ibu akan menjadi dasar pengembangan konten, assessment, dan desain pembelajaran PharmVR.\n\nTerima kasih.",
            ],
            SurveyDistributionBatch::AUDIENCE_PRACTITIONER => [
                'label' => 'Praktisi / Ahli CPOB',
                'missing_cta' => 'Create Practitioner Interview Form',
                'create_route' => route('admin.surveys.analysis.create-practitioner-interview', ['survey' => $mainSurvey]),
                'whatsapp' => 'Yth. Bapak/Ibu Praktisi/Ahli CPOB, saya sedang mengembangkan PharmVR, media pembelajaran VR berbasis CPOB/GMP untuk pendidikan farmasi industri. Mohon kesediaan Bapak/Ibu memberikan masukan melalui form wawancara terstruktur berikut: {link}. Identitas dapat menggunakan inisial dan nama industri dapat dikosongkan jika bersifat rahasia. Terima kasih.',
                'email' => "Yth. Bapak/Ibu Praktisi/Ahli CPOB,\n\nSaya {researcher} sedang mengembangkan PharmVR, media pembelajaran VR berbasis CPOB/GMP untuk pendidikan farmasi industri.\n\nMohon kesediaan Bapak/Ibu memberikan masukan melalui form wawancara terstruktur berikut:\n{link}\n\nIdentitas dapat menggunakan inisial dan nama industri dapat dikosongkan jika bersifat rahasia.\n\nTerima kasih.",
            ],
        ];

        return collect($configs)
            ->map(function (array $config, string $audience) use ($instruments, $user, $batches): array {
                $instrument = $instruments[$audience] ?? null;
                $link = $instrument ? route('survey.show', ['survey' => $instrument->slug]) : null;

                return [
                    'audience' => $audience,
                    'label' => $config['label'],
                    'survey' => $instrument,
                    'link' => $link,
                    'is_ready' => $instrument?->canReceiveResponses() ?? false,
                    'intro_complete' => $instrument ? $this->introComplete($instrument) : false,
                    'consent_required' => (bool) ($instrument?->require_consent_before_start),
                    'question_count' => $instrument?->questions->count() ?? 0,
                    'response_count' => $this->officialResponses($instrument),
                    'builder_route' => $instrument ? route('admin.surveys.builder.index', ['survey' => $instrument]) : null,
                    'responses_route' => $instrument ? route('admin.surveys.responses.index', ['survey' => $instrument]) : null,
                    'create_route' => $config['create_route'] ?? null,
                    'missing_cta' => $config['missing_cta'],
                    'whatsapp_message' => $this->fillTemplate($config['whatsapp'], $instrument, $link, $user),
                    'email_message' => $this->fillTemplate($config['email'], $instrument, $link, $user),
                    'batch' => $this->batchSummary($batches->get($audience)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SurveyValidationRound>  $rounds
     * @param  Collection<string, SurveyDistributionBatch>  $batches
     * @return array<string, mixed>
     */
    private function validationPanel(Survey $survey, Collection $rounds, User $user, Collection $batches): array
    {
        return [
            'rounds' => $rounds->map(fn (SurveyValidationRound $round): array => [
                'round' => $round,
                'report_route' => route('admin.surveys.validation.report', ['survey' => $survey, 'round' => $round]),
                'assignments' => $round->assignments->map(fn (SurveyValidationAssignment $assignment): array => [
                    'assignment' => $assignment,
                    'name' => $assignment->validator?->name ?: 'Validator',
                    'email' => $assignment->validator?->email,
                    'status_label' => SurveyValidationAssignment::STATUS_LABELS[$assignment->status] ?? $assignment->status,
                    'has_token' => filled($assignment->token_hash),
                    'generate_route' => route('admin.surveys.distribution.validation.generate-link', ['survey' => $survey, 'assignment' => $assignment]),
                    'revoke_route' => route('admin.surveys.distribution.validation.revoke-link', ['survey' => $survey, 'assignment' => $assignment]),
                    'can_revoke' => ! $assignment->isSubmitted() && ! $assignment->isRevoked(),
                    'whatsapp_message' => $this->fillTemplate(
                        'Yth. Bapak/Ibu Validator, mohon kesediaan Bapak/Ibu untuk menilai kelayakan instrumen penelitian PharmVR melalui link berikut: {link}. Penilaian dilakukan terhadap relevansi isi, kejelasan bahasa, kesesuaian konstruk, keterukuran, kelayakan penggunaan, dan aspek etika/kerahasiaan. Terima kasih atas bantuan dan masukannya.',
                        $survey,
                        '[regenerate link terlebih dahulu]',
                        $user,
                    ),
                    'email_message' => $this->fillTemplate(
                        "Yth. Bapak/Ibu Validator,\n\nMohon kesediaan Bapak/Ibu untuk menilai kelayakan instrumen penelitian PharmVR melalui link berikut:\n{link}\n\nPenilaian dilakukan terhadap relevansi isi, kejelasan bahasa, kesesuaian konstruk, keterukuran, kelayakan penggunaan, dan aspek etika/kerahasiaan.\n\nTerima kasih atas bantuan dan masukannya.",
                        $survey,
                        '[regenerate link terlebih dahulu]',
                        $user,
                    ),
                ])->values()->all(),
            ])->values()->all(),
            'batch' => $this->batchSummary($batches->get(SurveyDistributionBatch::AUDIENCE_EXPERT_VALIDATOR)),
        ];
    }

    /**
     * @param  Collection<int, SurveyReadabilityRound>  $rounds
     * @param  Collection<string, SurveyDistributionBatch>  $batches
     * @return array<string, mixed>
     */
    private function readabilityPanel(Survey $survey, Collection $rounds, User $user, Collection $batches): array
    {
        return [
            'rounds' => $rounds->map(fn (SurveyReadabilityRound $round): array => [
                'round' => $round,
                'report_route' => route('admin.surveys.readability.report', ['survey' => $survey, 'round' => $round]),
                'participants' => $round->participants->map(fn (SurveyReadabilityParticipant $participant): array => [
                    'participant' => $participant,
                    'name' => $participant->participant_name ?: 'Pilot participant',
                    'email' => $participant->participant_email,
                    'status_label' => SurveyReadabilityParticipant::STATUS_LABELS[$participant->status] ?? $participant->status,
                    'has_token' => filled($participant->token_hash),
                    'generate_route' => route('admin.surveys.distribution.readability.generate-link', ['survey' => $survey, 'participant' => $participant]),
                    'revoke_route' => route('admin.surveys.distribution.readability.revoke-link', ['survey' => $survey, 'participant' => $participant]),
                    'can_revoke' => ! $participant->isSubmitted() && ! $participant->isRevoked(),
                    'whatsapp_message' => $this->fillTemplate(
                        'Yth. Bapak/Ibu/Saudara/i, mohon kesediaannya membantu uji keterbacaan instrumen penelitian PharmVR melalui link berikut: {link}. Penilaian ini bukan menjawab kuesioner utama, tetapi menilai apakah butir pertanyaan mudah dipahami, tidak ambigu, dan pilihan jawabannya jelas. Terima kasih.',
                        $survey,
                        '[regenerate link terlebih dahulu]',
                        $user,
                    ),
                    'email_message' => $this->fillTemplate(
                        "Yth. Bapak/Ibu/Saudara/i,\n\nMohon kesediaannya membantu uji keterbacaan instrumen penelitian PharmVR melalui link berikut:\n{link}\n\nPenilaian ini bukan menjawab kuesioner utama, tetapi menilai apakah butir pertanyaan mudah dipahami, tidak ambigu, dan pilihan jawabannya jelas.\n\nTerima kasih.",
                        $survey,
                        '[regenerate link terlebih dahulu]',
                        $user,
                    ),
                ])->values()->all(),
            ])->values()->all(),
            'batch' => $this->batchSummary($batches->get(SurveyDistributionBatch::AUDIENCE_READABILITY_PARTICIPANT)),
        ];
    }

    /**
     * @param  array<string, Survey|null>  $instruments
     * @param  Collection<int, SurveyValidationRound>  $validationRounds
     * @param  Collection<int, SurveyReadabilityRound>  $readabilityRounds
     * @param  Collection<string, SurveyDistributionBatch>  $batches
     * @return array<string, mixed>
     */
    private function supervisorPanel(Survey $survey, array $instruments, Collection $validationRounds, Collection $readabilityRounds, User $user, Collection $batches): array
    {
        $links = [
            'Analysis dashboard' => route('admin.surveys.analysis.index', ['survey' => $survey]),
            'Analysis printable report' => route('admin.surveys.analysis.report', ['survey' => $survey]),
            'Distribution printable package' => route('admin.surveys.distribution.report', ['survey' => $survey]),
        ];

        if ($validationRounds->isNotEmpty()) {
            $links['Latest validation report'] = route('admin.surveys.validation.report', ['survey' => $survey, 'round' => $validationRounds->first()]);
        }

        if ($readabilityRounds->isNotEmpty()) {
            $links['Latest readability report'] = route('admin.surveys.readability.report', ['survey' => $survey, 'round' => $readabilityRounds->first()]);
        }

        $instrumentLines = collect($instruments)
            ->filter()
            ->map(fn (Survey $instrument): string => '- '.$instrument->title.' ('.route('admin.surveys.builder.index', ['survey' => $instrument]).')')
            ->values()
            ->join("\n");

        $message = "Yth. Bapak/Ibu Tim Promotor,\n\nBerikut saya lampirkan paket instrumen dan laporan sementara tahap Analysis ADDIE PharmVR. Paket ini mencakup kuesioner mahasiswa, kuesioner dosen, form wawancara praktisi, validasi ahli, uji keterbacaan, dashboard analysis, dan matriks sintesis. Mohon arahan dan masukan Bapak/Ibu.\n\nProject: ".($survey->project?->title ?? 'MyRiset Project')."\nSurvey utama: {$survey->title}\n\nInstrumen:\n{$instrumentLines}\n\nLink admin memerlukan login. Untuk pembimbing tanpa akun, gunakan PDF/printable report.";

        return [
            'links' => $links,
            'message' => $message,
            'email_message' => $this->fillTemplate($message, $survey, route('admin.surveys.analysis.report', ['survey' => $survey]), $user),
            'batch' => $this->batchSummary($batches->get(SurveyDistributionBatch::AUDIENCE_SUPERVISOR)),
        ];
    }

    /**
     * @param  Collection<string, SurveyDistributionBatch>  $batches
     * @return array<string, mixed>
     */
    private function batchPanels(Collection $batches): array
    {
        return collect(SurveyDistributionBatch::AUDIENCES)
            ->mapWithKeys(fn (string $audience): array => [$audience => $this->batchSummary($batches->get($audience))])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function batchSummary(?SurveyDistributionBatch $batch): ?array
    {
        if (! $batch) {
            return null;
        }

        return [
            'id' => $batch->getKey(),
            'title' => $batch->title,
            'message_subject' => $batch->message_subject,
            'message_body' => $batch->message_body,
            'deadline' => $batch->deadline,
            'status' => $batch->status,
            'status_label' => SurveyDistributionBatch::STATUS_LABELS[$batch->status] ?? $batch->status,
            'notes' => $batch->notes,
            'deadline_state' => $this->deadlineState($batch),
        ];
    }

    private function deadlineState(?SurveyDistributionBatch $batch): ?string
    {
        if (! $batch?->deadline) {
            return null;
        }

        $days = today()->diffInDays($batch->deadline, false);

        if ($days < 0) {
            return abs((int) $days).' days overdue';
        }

        if ($days === 0) {
            return 'Due today';
        }

        return ((int) $days).' days remaining';
    }

    private function introComplete(Survey $survey): bool
    {
        return filled($survey->intro_text)
            && filled($survey->estimated_duration)
            && filled($survey->privacy_statement)
            && filled($survey->respondent_instruction);
    }

    private function officialResponses(?Survey $survey): int
    {
        if (! $survey) {
            return 0;
        }

        return $survey->responses
            ->where('is_test_response', false)
            ->where('excluded_from_analysis', false)
            ->count();
    }

    private function fillTemplate(string $template, ?Survey $survey, ?string $link, User $user): string
    {
        return strtr($template, [
            '{link}' => $link ?: '[link belum tersedia]',
            '{duration}' => $survey?->estimated_duration ?: '10-15 menit',
            '{researcher}' => $user->name ?: 'peneliti',
            '{project}' => $survey?->project?->title ?: 'penelitian PharmVR',
            '{instrument}' => $survey?->title ?: 'instrumen penelitian',
            '{privacy}' => $this->privacySummary($survey),
            '{deadline}' => '[tanggal batas pengisian]',
            '{contact}' => '[kontak peneliti]',
        ]);
    }

    private function privacySummary(?Survey $survey): string
    {
        return Str::limit((string) ($survey?->privacy_statement ?: 'Data akan dijaga kerahasiaannya.'), 180, '...');
    }
}
