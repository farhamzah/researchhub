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

class CreateLecturerNeedsAnalysisQuestionnaireAction
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
                    'title' => 'Kuesioner Analisis Kebutuhan Dosen terhadap Media Pembelajaran Virtual Reality untuk CPOB/GMP Farmasi Industri (PharmVR)',
                    'description' => 'Instrumen analisis kebutuhan dosen untuk pembelajaran CPOB/GMP, farmasi industri, CPL/CPMK/OBE, fitur pembelajaran, dan kesiapan implementasi PharmVR.',
                    'identity_mode' => Survey::IDENTITY_HIDDEN,
                    'instrument_type' => Survey::INSTRUMENT_ANALYSIS_LECTURER,
                    'parent_survey_id' => $mainSurvey->getKey(),
                    'analysis_group_key' => $groupKey,
                    ...SurveyIntroTemplates::lecturerPharmVr(),
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
            ->where('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER)
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
            return;
        }

        foreach ($this->sections() as $pageIndex => $section) {
            $page = $survey->pages()->firstOrCreate(
                ['title' => $section['title']],
                ['description' => $section['description'] ?? null, 'sort_order' => $pageIndex + 1],
            );

            foreach ($section['questions'] as $questionIndex => $definition) {
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
                        'sort_order' => $questionIndex + 1,
                    ],
                );

                $this->ensureScoring($question, $definition, $indicators);
            }
        }
    }

    private function fillBlankMetadata(Survey $survey): void
    {
        $updates = [];

        foreach (SurveyIntroTemplates::lecturerPharmVr() as $key => $value) {
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
            'profil_pengalaman_mengajar' => 'Profil dan Pengalaman Mengajar',
            'pengalaman_mengajar_cpob_gmp' => 'Pengalaman Mengajar CPOB/GMP',
            'kesulitan_pembelajaran_mahasiswa' => 'Kesulitan Pembelajaran Mahasiswa',
            'kebutuhan_media_vr_pharmvr' => 'Kebutuhan Media VR/PharmVR',
            'kesesuaian_cpl_cpmk_obe_tpack' => 'Kesesuaian CPL/CPMK/OBE/TPACK',
            'kesiapan_implementasi_pembelajaran' => 'Kesiapan Implementasi Pembelajaran',
            'prioritas_scene_fitur' => 'Prioritas Scene dan Fitur',
            'masukan_terbuka' => 'Masukan Terbuka',
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
        $attributes = [
            'survey_id' => $question->survey_id,
            'survey_indicator_id' => $indicator?->getKey(),
            'is_scored' => $mode === 'score',
            'score_min' => $mode === 'score' ? 1 : null,
            'score_max' => $mode === 'score' ? 5 : null,
            'weight' => 1,
            'is_reverse_scored' => false,
            'settings' => match ($mode) {
                'risk' => ['risk_item' => true, 'not_positive_readiness' => true, 'descriptive' => true],
                'descriptive' => ['descriptive' => true],
                default => null,
            },
        ];

        $question->scoring()->updateOrCreate([], $attributes);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function optionsFor(array $definition): ?array
    {
        if (($definition['type'] ?? null) === SurveyQuestion::TYPE_LIKERT) {
            return ['scale' => [1, 2, 3, 4, 5]];
        }

        return isset($definition['options']) ? ['choices' => $definition['options']] : null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function settingsFor(array $definition): ?array
    {
        $settings = [];

        if (($definition['type'] ?? null) === SurveyQuestion::TYPE_LIKERT) {
            $settings['scale'] = [1, 2, 3, 4, 5];
        }

        if (isset($definition['max_selections'])) {
            $settings['max_selections'] = $definition['max_selections'];
        }

        return $settings === [] ? null : $settings;
    }

    /**
     * @return array<int, array{title: string, description?: string|null, questions: array<int, array<string, mixed>>}>
     */
    private function sections(): array
    {
        return [
            ['title' => 'Persetujuan dan Kriteria Responden', 'questions' => [
                $this->q('L_A1', SurveyQuestion::TYPE_CONSENT, 'Saya telah membaca penjelasan mengenai tujuan kuesioner ini dan bersedia mengisi kuesioner secara sukarela.', true, indicator: 'profil_pengalaman_mengajar'),
                $this->q('L_A2', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Apakah Bapak/Ibu merupakan dosen atau pengajar yang terlibat dalam mata kuliah farmasi industri, teknologi farmasi, CPOB/GMP, atau bidang terkait?', true, ['Ya', 'Tidak'], 'profil_pengalaman_mengajar'),
                $this->q('L_A3', SurveyQuestion::TYPE_CONSENT, 'Saya memahami bahwa data yang dikumpulkan akan digunakan untuk kebutuhan analisis pengembangan media pembelajaran PharmVR dan dilaporkan secara agregat tanpa menampilkan identitas pribadi.', true, indicator: 'profil_pengalaman_mengajar'),
            ]],
            ['title' => 'Profil Dosen', 'questions' => [
                $this->q('L_B1', SurveyQuestion::TYPE_SHORT_TEXT, 'Nama dosen/responden', true, indicator: 'profil_pengalaman_mengajar'),
                $this->q('L_B2', SurveyQuestion::TYPE_SHORT_TEXT, 'Perguruan tinggi/institusi asal', false, indicator: 'profil_pengalaman_mengajar'),
                $this->q('L_B3', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Bidang keahlian utama Bapak/Ibu', true, ['Farmasi Industri', 'Teknologi Farmasi', 'CPOB/GMP', 'Manajemen Mutu Farmasi', 'Farmasetika', 'Pendidikan Farmasi', 'Lainnya'], 'profil_pengalaman_mengajar'),
                $this->q('L_B4', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Lama pengalaman mengajar bidang terkait farmasi industri/CPOB/GMP', true, ['< 1 tahun', '1-3 tahun', '4-6 tahun', '7-10 tahun', '> 10 tahun'], 'profil_pengalaman_mengajar'),
                $this->q('L_B5', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Materi yang pernah Bapak/Ibu ajarkan atau dampingi', false, ['CPOB/GMP', 'Alur produksi obat', 'Teknologi sediaan padat/tablet', 'QA/QC', 'Dokumentasi batch record', 'Validasi/kualifikasi', 'Manajemen risiko mutu', 'Praktikum farmasi industri', 'Kunjungan industri', 'Lainnya'], 'profil_pengalaman_mengajar'),
            ]],
            ['title' => 'Pengalaman Pembelajaran CPOB/GMP', 'questions' => [
                $this->q('L_C1', SurveyQuestion::TYPE_LIKERT, 'Saya telah mengajarkan atau mendampingi pembelajaran yang berkaitan dengan CPOB/GMP atau farmasi industri.', true, indicator: 'pengalaman_mengajar_cpob_gmp', scoring: 'score'),
                ...$this->likert('kesulitan_pembelajaran_mahasiswa', [
                    'L_C2' => 'Mahasiswa sering membutuhkan contoh visual untuk memahami layout dan ruang produksi farmasi.',
                    'L_C3' => 'Mahasiswa sering mengalami kesulitan memahami alur personel dan alur material dalam industri farmasi.',
                    'L_C4' => 'Mahasiswa sering mengalami kesulitan memahami hubungan antara Produksi, QA, QC, Warehouse, PPIC, Purchasing, dan Engineering.',
                    'L_C5' => 'Mahasiswa membutuhkan contoh nyata untuk memahami dokumentasi seperti batch record, deviation, CAPA, dan release produk.',
                ]),
                $this->q('L_C6', SurveyQuestion::TYPE_LIKERT, 'Pembelajaran CPOB/GMP membutuhkan media yang dapat menggambarkan prosedur dan alur kerja secara lebih kontekstual.', true, indicator: 'kebutuhan_media_vr_pharmvr', scoring: 'score'),
            ]],
            ['title' => 'Kesesuaian Pembelajaran dengan CPL/CPMK/OBE', 'questions' => $this->likert('kesesuaian_cpl_cpmk_obe_tpack', [
                'L_D1' => 'Media pembelajaran CPOB/GMP perlu mendukung pencapaian CPL/CPMK mata kuliah farmasi industri.',
                'L_D2' => 'Media pembelajaran perlu membantu mahasiswa memahami keterkaitan teori, prosedur, dan penerapan industri.',
                'L_D3' => 'Media pembelajaran perlu menyediakan aktivitas yang dapat diamati dan dinilai sesuai capaian pembelajaran.',
                'L_D4' => 'Pretest dan posttest diperlukan untuk melihat peningkatan pemahaman mahasiswa.',
                'L_D5' => 'Aktivitas pembelajaran berbasis simulasi dapat mendukung pendekatan Outcome-Based Education.',
                'L_D6' => 'Media VR perlu dilengkapi indikator capaian belajar, progress, dan umpan balik.',
            ])],
            ['title' => 'Kebutuhan Media VR/PharmVR', 'questions' => $this->likert('kebutuhan_media_vr_pharmvr', [
                'L_E1' => 'Media Virtual Reality dapat membantu mahasiswa memahami lingkungan industri farmasi secara lebih konkret.',
                'L_E2' => 'PharmVR perlu menampilkan tahapan masuk area produksi seperti hygiene, gowning, dan airlock.',
                'L_E3' => 'PharmVR perlu menampilkan simulasi alur produksi tablet non-steril.',
                'L_E4' => 'PharmVR perlu menampilkan peran dan interaksi antarbagian industri farmasi.',
                'L_E5' => 'PharmVR perlu menyediakan panel informasi CPOB/GMP yang ringkas dan mudah dipahami.',
                'L_E6' => 'PharmVR perlu memberikan umpan balik saat mahasiswa melakukan kesalahan prosedur.',
                'L_E7' => 'PharmVR perlu menyediakan hasil belajar atau laporan progress mahasiswa.',
            ])],
            ['title' => 'Kesiapan Implementasi', 'questions' => [
                ...$this->likert('kesiapan_implementasi_pembelajaran', [
                    'L_F1' => 'Media VR berpotensi digunakan sebagai pendukung pembelajaran farmasi industri di perguruan tinggi.',
                    'L_F2' => 'Dosen memerlukan panduan penggunaan PharmVR sebelum digunakan dalam pembelajaran.',
                    'L_F3' => 'PharmVR perlu dapat diakses melalui perangkat yang realistis tersedia di kampus, seperti laptop, smartphone, atau headset VR.',
                    'L_F4' => 'Penggunaan PharmVR perlu didampingi instruksi yang jelas agar tidak membingungkan mahasiswa.',
                    'L_F5' => 'Waktu penggunaan PharmVR perlu disesuaikan dengan durasi perkuliahan atau praktikum.',
                ]),
                $this->q('L_F6', SurveyQuestion::TYPE_LIKERT, 'Potensi ketidaknyamanan penggunaan VR seperti pusing, mual, atau lelah perlu diperhatikan dalam implementasi pembelajaran.', true, indicator: 'kesiapan_implementasi_pembelajaran', scoring: 'risk'),
            ]],
            ['title' => 'Prioritas Scene dan Fitur', 'questions' => [
                $this->q('L_G1', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 3 scene PharmVR yang paling penting untuk dikembangkan dari sudut pandang dosen.', true, ['Hygiene dan gowning', 'Airlock dan alur personel', 'Weighing', 'Granulation', 'Final mixing', 'Tabletting', 'Coating', 'Blistering/primary packaging', 'Secondary packing', 'QC Lab', 'QA Office', 'Warehouse', 'PPIC', 'Purchasing', 'Engineering'], 'prioritas_scene_fitur', 'descriptive', 3),
                $this->q('L_G2', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Pilih maksimal 3 fitur PharmVR yang paling penting untuk mendukung pembelajaran.', true, ['Avatar/instruktur virtual', 'Panel SOP/CPOB', 'Checklist aktivitas', 'Simulasi kesalahan dan feedback', 'Pretest dan posttest', 'Dashboard progress mahasiswa', 'Sertifikat/hasil belajar', 'Denah pabrik interaktif', 'Mode akses laptop/mobile/headset VR', 'Materi pendukung'], 'prioritas_scene_fitur', 'descriptive', 3),
            ]],
            ['title' => 'Masukan Terbuka', 'questions' => [
                $this->q('L_H1', SurveyQuestion::TYPE_LONG_TEXT, 'Materi CPOB/GMP apa yang menurut Bapak/Ibu paling sulit dipahami mahasiswa?', false, indicator: 'masukan_terbuka'),
                $this->q('L_H2', SurveyQuestion::TYPE_LONG_TEXT, 'Scene atau alur industri farmasi apa yang paling perlu divisualisasikan dalam PharmVR?', false, indicator: 'masukan_terbuka'),
                $this->q('L_H3', SurveyQuestion::TYPE_LONG_TEXT, 'Fitur apa yang perlu ada agar PharmVR mudah digunakan dalam pembelajaran?', false, indicator: 'masukan_terbuka'),
                $this->q('L_H4', SurveyQuestion::TYPE_LONG_TEXT, 'Kendala apa yang perlu diantisipasi jika PharmVR digunakan dalam pembelajaran?', false, indicator: 'masukan_terbuka'),
                $this->q('L_H5', SurveyQuestion::TYPE_LONG_TEXT, 'Saran lain untuk pengembangan PharmVR sebagai media pembelajaran farmasi industri.', false, indicator: 'masukan_terbuka'),
            ]],
        ];
    }

    /**
     * @param  array<string, string>  $items
     * @return array<int, array<string, mixed>>
     */
    private function likert(string $indicator, array $items): array
    {
        return collect($items)
            ->map(fn (string $label, string $key): array => $this->q($key, SurveyQuestion::TYPE_LIKERT, $label, true, indicator: $indicator, scoring: 'score'))
            ->values()
            ->all();
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
