<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\Surveys\Support\SurveyIntroTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePractitionerInterviewFormAction
{
    public function __construct(private readonly CreateSurveyAction $createSurvey) {}

    public function handle(User $user, Survey $mainSurvey, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('runAnalysis', $mainSurvey);
        $mainSurvey->loadMissing('project');

        $groupKey = $mainSurvey->analysis_group_key ?: Survey::ANALYSIS_GROUP_PHARMVR_ADDIE;

        return DB::transaction(function () use ($user, $mainSurvey, $groupKey, $request): Survey {
            $this->ensureMainSurveyMetadata($mainSurvey, $groupKey);

            $survey = $this->existingInstrument($mainSurvey, $groupKey)
                ?: $this->createSurvey->handle($user, $mainSurvey->project, [
                    'title' => 'Pedoman Wawancara Praktisi/Ahli CPOB untuk Analisis Kebutuhan PharmVR',
                    'description' => 'Pedoman wawancara semi-terstruktur untuk praktisi atau ahli CPOB/GMP terkait scene, alur proses, risiko miskonsepsi, fitur, dan evaluasi PharmVR.',
                    'identity_mode' => Survey::IDENTITY_HIDDEN,
                    'instrument_type' => Survey::INSTRUMENT_PRACTITIONER_INTERVIEW,
                    'parent_survey_id' => $mainSurvey->getKey(),
                    'analysis_group_key' => $groupKey,
                    ...SurveyIntroTemplates::practitionerPharmVr(),
                ], $request);

            $this->fillMissingTemplate($survey);

            return $survey->fresh(['pages.questions.scoring.indicator', 'questions.scoring.indicator', 'indicators']);
        });
    }

    private function existingInstrument(Survey $mainSurvey, string $groupKey): ?Survey
    {
        return Survey::query()
            ->where('project_id', $mainSurvey->project_id)
            ->where('parent_survey_id', $mainSurvey->getKey())
            ->where('analysis_group_key', $groupKey)
            ->where('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW)
            ->first();
    }

    private function ensureMainSurveyMetadata(Survey $mainSurvey, string $groupKey): void
    {
        $updates = [
            'analysis_group_key' => $groupKey,
            'instrument_type' => $mainSurvey->instrument_type ?: Survey::INSTRUMENT_ANALYSIS_STUDENT,
        ];

        foreach (SurveyIntroTemplates::studentPharmVr() as $key => $value) {
            $updates[$key] = $key === 'require_consent_before_start'
                ? ($mainSurvey->require_consent_before_start ?: $value)
                : (filled($mainSurvey->{$key}) ? $mainSurvey->{$key} : $value);
        }

        if (collect($updates)->contains(fn (mixed $value, string $key): bool => $mainSurvey->{$key} !== $value)) {
            $mainSurvey->forceFill($updates)->save();
        }
    }

    private function fillMissingTemplate(Survey $survey): void
    {
        $this->fillBlankMetadata($survey);
        $indicators = $this->ensureIndicators($survey);

        if ($this->hasRealResponses($survey)) {
            throw ValidationException::withMessages([
                'template' => 'Normalization is blocked because this survey already has real responses.',
            ]);
        }

        $sortOrder = 0;

        foreach ($this->sections() as $pageIndex => $section) {
            $page = $survey->pages()->firstOrCreate(
                ['title' => $section['title']],
                ['description' => $section['description'] ?? null, 'sort_order' => $pageIndex + 1],
            );
            $page->forceFill([
                'description' => $section['description'] ?? $page->description,
                'sort_order' => $pageIndex + 1,
            ])->save();

            foreach ($section['questions'] as $definition) {
                $sortOrder++;
                $question = $survey->questions()->firstOrCreate(
                    ['question_key' => $definition['key']],
                    [
                        'page_id' => $page->getKey(),
                        'type' => $definition['type'],
                        'label' => $definition['label'],
                        'help_text' => $definition['help_text'] ?? null,
                        'options' => $this->optionsFor($definition),
                        'settings' => $this->settingsFor($definition),
                        'is_required' => $definition['required'],
                        'sort_order' => $sortOrder,
                    ],
                );
                $question->forceFill([
                    'page_id' => $page->getKey(),
                    'type' => $definition['type'],
                    'label' => $definition['label'],
                    'help_text' => $definition['help_text'] ?? null,
                    'options' => $this->optionsFor($definition),
                    'settings' => $this->settingsFor($definition),
                    'is_required' => $definition['required'],
                    'sort_order' => $sortOrder,
                ])->save();

                $this->ensureScoring($question, $definition, $indicators);
            }
        }
    }

    private function fillBlankMetadata(Survey $survey): void
    {
        $updates = [];

        foreach (SurveyIntroTemplates::practitionerPharmVr() as $key => $value) {
            if ($key === 'require_consent_before_start') {
                $updates[$key] = $survey->require_consent_before_start ?: $value;

                continue;
            }

            if (blank($survey->{$key})) {
                $updates[$key] = $value;
            }
        }

        if ($updates !== []) {
            $survey->forceFill($updates)->save();
        }
    }

    /**
     * @return array<string, SurveyIndicator>
     */
    private function ensureIndicators(Survey $survey): array
    {
        $definitions = [
            'profil_narasumber_keahlian' => 'Profil Narasumber dan Keahlian',
            'fokus_wawancara_tema_utama' => 'Fokus Wawancara dan Tema Utama',
            'kebutuhan_konten_cpob_gmp' => 'Kebutuhan Konten CPOB/GMP',
            'validasi_alur_produksi_scene' => 'Validasi Alur Produksi dan Scene',
            'risiko_miskonsepsi_ketidakakuratan' => 'Risiko Miskonsepsi dan Ketidakakuratan',
            'prioritas_scene_fitur' => 'Prioritas Scene dan Fitur',
            'implementasi_kelayakan_rekomendasi_industri' => 'Implementasi, Kelayakan, dan Rekomendasi Industri',
        ];

        return collect($definitions)
            ->mapWithKeys(function (string $name, string $slug) use ($survey, $definitions): array {
                $index = array_search($slug, array_keys($definitions), true);

                return [
                    $slug => $survey->indicators()->firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $name, 'sort_order' => (int) $index + 1],
                    ),
                ];
            })->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, SurveyIndicator>  $indicators
     */
    private function ensureScoring(SurveyQuestion $question, array $definition, array $indicators): void
    {
        $mode = $definition['scoring'] ?? (isset($definition['indicator']) ? 'descriptive' : null);

        if ($mode === null) {
            return;
        }

        $indicator = $indicators[$definition['indicator'] ?? ''] ?? null;

        $question->scoring()->updateOrCreate([], [
            'survey_id' => $question->survey_id,
            'survey_indicator_id' => $indicator?->getKey(),
            'is_scored' => false,
            'score_min' => null,
            'score_max' => null,
            'weight' => 1,
            'is_reverse_scored' => false,
            'settings' => ['descriptive' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function optionsFor(array $definition): ?array
    {
        return isset($definition['options']) ? ['choices' => $definition['options']] : null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function settingsFor(array $definition): ?array
    {
        return isset($definition['max_selections'])
            ? ['max_selections' => $definition['max_selections']]
            : null;
    }

    /**
     * @return array<int, array{title: string, questions: array<int, array<string, mixed>>}>
     */
    private function sections(): array
    {
        return [
            ['title' => 'Persetujuan dan Profil Narasumber', 'questions' => [
                $this->q('P_A1', SurveyQuestion::TYPE_CONSENT, 'Narasumber telah memperoleh penjelasan mengenai tujuan wawancara dan bersedia memberikan masukan secara sukarela.', true, indicator: 'profil_narasumber_keahlian'),
                $this->q('P_A6', SurveyQuestion::TYPE_CONSENT, 'Narasumber memahami bahwa data wawancara digunakan untuk analisis kebutuhan pengembangan PharmVR dan dapat disamarkan dalam laporan penelitian.', true, indicator: 'profil_narasumber_keahlian'),
                $this->q('P_A3', SurveyQuestion::TYPE_SHORT_TEXT, 'Nama atau inisial narasumber', true, indicator: 'profil_narasumber_keahlian'),
                $this->q('P_A4', SurveyQuestion::TYPE_SHORT_TEXT, 'Institusi/perusahaan/afiliasi', false, indicator: 'profil_narasumber_keahlian'),
                $this->q('P_A2', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Kategori narasumber', true, ['Praktisi industri farmasi', 'Ahli CPOB/GMP', 'QA/QC', 'Produksi', 'Regulatory/Compliance', 'Akademisi dengan keahlian CPOB/GMP', 'Lainnya'], 'profil_narasumber_keahlian', 'descriptive'),
                $this->q('P_F4', SurveyQuestion::TYPE_SHORT_TEXT, 'Jabatan/bidang', true, indicator: 'profil_narasumber_keahlian'),
                $this->q('P_A5', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Lama pengalaman terkait industri farmasi atau CPOB/GMP', true, ['< 1 tahun', '1-3 tahun', '4-6 tahun', '7-10 tahun', '> 10 tahun'], 'profil_narasumber_keahlian', 'descriptive'),
            ]],
            ['title' => 'Pandangan Umum terhadap PharmVR', 'questions' => [
                $this->q('P_F1', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Secara umum, apakah media VR seperti PharmVR layak dikembangkan sebagai media pembelajaran farmasi industri?', true, ['Layak', 'Layak dengan revisi/penyesuaian', 'Belum layak', 'Tidak dapat menilai'], 'implementasi_kelayakan_rekomendasi_industri', 'descriptive'),
                $this->q('P_F2', SurveyQuestion::TYPE_LONG_TEXT, 'Apa alasan dari penilaian tersebut?', true, indicator: 'implementasi_kelayakan_rekomendasi_industri'),
                $this->q('P_E4', SurveyQuestion::TYPE_LONG_TEXT, 'Apakah simulasi VR relevan untuk membantu mahasiswa memahami CPOB/GMP? Mohon jelaskan.', true, indicator: 'implementasi_kelayakan_rekomendasi_industri'),
            ]],
            ['title' => 'Kebutuhan Konten CPOB/GMP', 'questions' => [
                $this->q('P_B1', SurveyQuestion::TYPE_LONG_TEXT, 'Menurut Bapak/Ibu, kompetensi CPOB/GMP apa yang paling penting dipahami mahasiswa farmasi sebelum masuk ke dunia industri?', true, indicator: 'kebutuhan_konten_cpob_gmp'),
                $this->q('P_B2', SurveyQuestion::TYPE_LONG_TEXT, 'Bagian mana dari CPOB/GMP yang paling sering sulit dipahami oleh mahasiswa atau pemula?', true, indicator: 'kebutuhan_konten_cpob_gmp'),
                $this->q('P_D2', SurveyQuestion::TYPE_LONG_TEXT, 'Istilah atau konsep CPOB/GMP apa yang harus dijelaskan dengan hati-hati dalam PharmVR?', true, indicator: 'risiko_miskonsepsi_ketidakakuratan'),
            ]],
            ['title' => 'Validasi Alur Produksi dan Scene', 'questions' => [
                $this->q('P_C1', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 5 scene yang paling penting untuk divisualisasikan dalam PharmVR.', true, ['Hygiene dan gowning', 'Airlock', 'Production corridor', 'Weighing', 'Granulation', 'Final mixing', 'Tabletting', 'Coating', 'Blistering/primary packaging', 'Secondary packing', 'QC Lab', 'QA Office', 'Warehouse', 'PPIC', 'Purchasing', 'Engineering'], 'prioritas_scene_fitur', 'descriptive', 5),
                $this->q('P_C2', SurveyQuestion::TYPE_LONG_TEXT, 'Mengapa scene tersebut dianggap penting untuk pembelajaran mahasiswa?', true, indicator: 'validasi_alur_produksi_scene'),
                $this->q('P_C3', SurveyQuestion::TYPE_LONG_TEXT, 'Alur proses apa yang wajib ditampilkan agar simulasi terasa sesuai dengan praktik industri?', true, indicator: 'validasi_alur_produksi_scene'),
                $this->q('P_B4', SurveyQuestion::TYPE_LONG_TEXT, 'Bagaimana sebaiknya mahasiswa diperkenalkan pada hubungan antara Produksi, QA, QC, Warehouse, PPIC, Purchasing, dan Engineering?', true, indicator: 'validasi_alur_produksi_scene'),
                $this->q('P_D3', SurveyQuestion::TYPE_LONG_TEXT, 'Bagaimana sebaiknya PharmVR menjelaskan dokumentasi seperti batch record, deviation, CAPA, IPC, dan release produk?', true, indicator: 'validasi_alur_produksi_scene'),
            ]],
            ['title' => 'Risiko Miskonsepsi dan Batasan Simulasi', 'questions' => [
                $this->q('P_B3', SurveyQuestion::TYPE_LONG_TEXT, 'Kesalahan atau miskonsepsi apa yang sering terjadi dalam memahami alur produksi obat atau prinsip CPOB/GMP?', true, indicator: 'risiko_miskonsepsi_ketidakakuratan'),
                $this->q('P_D1', SurveyQuestion::TYPE_LONG_TEXT, 'Bagian mana dari simulasi farmasi industri yang paling berisiko menimbulkan miskonsepsi jika divisualisasikan secara tidak tepat?', true, indicator: 'risiko_miskonsepsi_ketidakakuratan'),
                $this->q('P_C4', SurveyQuestion::TYPE_LONG_TEXT, 'Batasan apa yang perlu diperhatikan agar simulasi tidak keliru atau terlalu menyederhanakan praktik CPOB/GMP?', true, indicator: 'risiko_miskonsepsi_ketidakakuratan'),
                $this->q('P_D4', SurveyQuestion::TYPE_LONG_TEXT, 'Apa indikator bahwa suatu aktivitas dalam simulasi sudah sesuai atau belum sesuai dengan prinsip CPOB/GMP?', true, indicator: 'validasi_alur_produksi_scene'),
            ]],
            ['title' => 'Fitur, Evaluasi, dan Rekomendasi', 'questions' => [
                $this->q('P_E1', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 5 fitur yang paling penting menurut Bapak/Ibu.', true, ['Avatar/instruktur virtual', 'Panel SOP/CPOB', 'Checklist aktivitas', 'Simulasi kesalahan dan feedback', 'Pretest dan posttest', 'Dashboard progress', 'Denah pabrik interaktif', 'Knowledge hotspot pada alat dan ruangan', 'Catatan dokumentasi', 'Mode akses laptop/mobile/headset VR', 'Sertifikat/hasil belajar'], 'prioritas_scene_fitur', 'descriptive', 5),
                $this->q('P_E2', SurveyQuestion::TYPE_LONG_TEXT, 'Jenis feedback apa yang sebaiknya diberikan ketika pengguna melakukan kesalahan prosedur?', true, indicator: 'prioritas_scene_fitur'),
                $this->q('P_E3', SurveyQuestion::TYPE_LONG_TEXT, 'Jenis evaluasi atau pertanyaan apa yang relevan untuk menilai pemahaman mahasiswa terhadap CPOB/GMP?', true, indicator: 'fokus_wawancara_tema_utama'),
                $this->q('P_F3', SurveyQuestion::TYPE_LONG_TEXT, 'Apa rekomendasi utama Bapak/Ibu untuk pengembangan PharmVR?', false, indicator: 'implementasi_kelayakan_rekomendasi_industri'),
            ]],
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private function q(
        string $key,
        string $type,
        string $label,
        bool $required,
        ?array $options = null,
        ?string $indicator = null,
        ?string $scoring = null,
        ?int $maxSelections = null,
        ?string $helpText = null,
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'required' => $required,
            'options' => $options,
            'indicator' => $indicator,
            'scoring' => $scoring,
            'max_selections' => $maxSelections,
            'help_text' => $helpText,
        ];
    }

    private function hasRealResponses(Survey $survey): bool
    {
        return $survey->responses()->official()->exists();
    }
}
