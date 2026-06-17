<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\SurveyQuestion;
use App\Models\SurveyScale;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PharmVrStudentNeedsSurveyTemplateService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function create(User $user, Survey $survey): array
    {
        $keys = collect($this->questions())->pluck('key');
        $existing = $survey->questions()->whereIn('question_key', $keys->all())->pluck('question_key');

        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'template' => 'Template cannot be created because these question keys already exist: '.$existing->join(', '),
            ]);
        }

        return DB::transaction(function () use ($user, $survey): array {
            $scale = $survey->scales()->firstOrCreate(
                ['slug' => 'likert-1-5'],
                [
                    'name' => 'Skala Likert 1-5',
                    'description' => '1 = Sangat tidak setuju sampai 5 = Sangat setuju.',
                    'sort_order' => 1,
                ],
            );

            $pages = collect($this->pages())
                ->mapWithKeys(fn (array $page): array => [
                    $page['title'] => $survey->pages()->firstOrCreate(
                        ['title' => $page['title']],
                        ['description' => $page['description'], 'sort_order' => $page['order']],
                    ),
                ]);

            $indicators = collect($this->indicators())
                ->mapWithKeys(fn (array $indicator): array => [
                    $indicator['name'] => $this->indicator($survey, $scale, $indicator),
                ]);

            foreach ($this->questions() as $index => $question) {
                $model = $survey->questions()->create([
                    'page_id' => $pages->get($question['page'])?->id,
                    'question_key' => $question['key'],
                    'type' => $question['type'],
                    'label' => $question['label'],
                    'help_text' => $question['help'] ?? null,
                    'options' => $question['options'] ?? null,
                    'settings' => $question['settings'] ?? null,
                    'is_required' => (bool) ($question['required'] ?? false),
                    'sort_order' => $index + 1,
                ]);

                $indicator = $indicators->get($question['indicator'] ?? '');
                if ($indicator instanceof SurveyIndicator && ($question['score'] ?? false)) {
                    $model->scoring()->create([
                        'survey_id' => $survey->id,
                        'survey_indicator_id' => $indicator->id,
                        'is_scored' => true,
                        'score_min' => 1,
                        'score_max' => 5,
                        'weight' => 1,
                        'is_reverse_scored' => false,
                    ]);
                } elseif (isset($question['indicator'])) {
                    $model->scoring()->create([
                        'survey_id' => $survey->id,
                        'survey_indicator_id' => $indicator?->id,
                        'is_scored' => false,
                        'weight' => 1,
                        'settings' => $question['settings_extra'] ?? null,
                    ]);
                }
            }

            $this->activityLogger->log('survey.pharmvr_student_needs_template_created', $user, $survey->project, $survey, [
                'survey_id' => $survey->id,
                'question_count' => count($this->questions()),
            ]);

            return [
                'pages' => $pages->count(),
                'indicators' => $indicators->count(),
                'questions' => count($this->questions()),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function previewMissing(Survey $survey): array
    {
        $questions = collect($this->questions());
        $templateKeys = $questions->pluck('key');
        $existingKeys = $survey->questions()->whereIn('question_key', $templateKeys->all())->pluck('question_key');
        $missingQuestions = $questions
            ->reject(fn (array $question): bool => $existingKeys->contains($question['key']))
            ->values();

        return [
            'template_count' => $questions->count(),
            'existing_count' => $existingKeys->count(),
            'missing_count' => $missingQuestions->count(),
            'existing_keys' => $existingKeys->values()->all(),
            'missing_keys' => $missingQuestions->pluck('key')->all(),
            'missing_pages' => $missingQuestions->pluck('page')->unique()->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fillMissing(User $user, Survey $survey): array
    {
        $existingKeys = $survey->questions()->pluck('question_key')->all();
        $missingQuestions = collect($this->questions())
            ->reject(fn (array $question): bool => in_array($question['key'], $existingKeys, true))
            ->values();

        if ($missingQuestions->isEmpty()) {
            throw ValidationException::withMessages([
                'template' => 'All PharmVR template question keys already exist in this survey.',
            ]);
        }

        return DB::transaction(function () use ($user, $survey, $missingQuestions): array {
            $scale = $survey->scales()->firstOrCreate(
                ['slug' => 'likert-1-5'],
                [
                    'name' => 'Skala Likert 1-5',
                    'description' => '1 = Sangat tidak setuju sampai 5 = Sangat setuju.',
                    'sort_order' => 1,
                ],
            );

            $pages = collect($this->pages())
                ->mapWithKeys(fn (array $page): array => [
                    $page['title'] => $survey->pages()->firstOrCreate(
                        ['title' => $page['title']],
                        ['description' => $page['description'], 'sort_order' => $page['order']],
                    ),
                ]);

            $indicators = collect($this->indicators())
                ->mapWithKeys(fn (array $indicator): array => [
                    $indicator['name'] => $this->indicator($survey, $scale, $indicator),
                ]);
            $sortOrder = (int) $survey->questions()->max('sort_order');

            foreach ($missingQuestions as $question) {
                $sortOrder++;
                $model = $survey->questions()->create([
                    'page_id' => $pages->get($question['page'])?->id,
                    'question_key' => $question['key'],
                    'type' => $question['type'],
                    'label' => $question['label'],
                    'help_text' => $question['help'] ?? null,
                    'options' => $question['options'] ?? null,
                    'settings' => $question['settings'] ?? null,
                    'is_required' => (bool) ($question['required'] ?? false),
                    'sort_order' => $sortOrder,
                ]);

                $indicator = $indicators->get($question['indicator'] ?? '');
                if ($indicator instanceof SurveyIndicator && ($question['score'] ?? false)) {
                    $model->scoring()->create([
                        'survey_id' => $survey->id,
                        'survey_indicator_id' => $indicator->id,
                        'is_scored' => true,
                        'score_min' => 1,
                        'score_max' => 5,
                        'weight' => 1,
                        'is_reverse_scored' => false,
                    ]);
                } elseif (isset($question['indicator'])) {
                    $model->scoring()->create([
                        'survey_id' => $survey->id,
                        'survey_indicator_id' => $indicator?->id,
                        'is_scored' => false,
                        'weight' => 1,
                        'settings' => $question['settings_extra'] ?? null,
                    ]);
                }
            }

            $this->activityLogger->log('survey.pharmvr_student_needs_template_filled_missing', $user, $survey->project, $survey, [
                'survey_id' => $survey->id,
                'question_count' => $missingQuestions->count(),
            ]);

            return [
                'pages' => $pages->count(),
                'indicators' => $indicators->count(),
                'questions' => $missingQuestions->count(),
            ];
        });
    }

    /**
     * @return array<int, array{title: string, description: string, order: int}>
     */
    private function pages(): array
    {
        return [
            ['title' => 'Persetujuan dan Kriteria Responden', 'description' => 'Konfirmasi persetujuan dan kriteria responden.', 'order' => 1],
            ['title' => 'Profil Responden', 'description' => 'Profil akademik responden.', 'order' => 2],
            ['title' => 'Pengalaman Pembelajaran CPOB/GMP', 'description' => 'Pengalaman awal mahasiswa dalam mempelajari CPOB/GMP.', 'order' => 3],
            ['title' => 'Kesulitan Belajar CPOB/GMP', 'description' => 'Kesulitan memahami proses dan konsep CPOB/GMP.', 'order' => 4],
            ['title' => 'Kebutuhan Media VR/PharmVR', 'description' => 'Kebutuhan media pembelajaran berbasis VR.', 'order' => 5],
            ['title' => 'Kesiapan Teknologi dan Penerimaan', 'description' => 'Kesiapan perangkat, penerimaan teknologi, dan kenyamanan VR.', 'order' => 6],
            ['title' => 'Prioritas Scene dan Fitur PharmVR', 'description' => 'Prioritas scene dan fitur yang dibutuhkan.', 'order' => 7],
            ['title' => 'Masukan Terbuka', 'description' => 'Masukan kualitatif responden.', 'order' => 8],
        ];
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    private function indicators(): array
    {
        return [
            ['name' => 'Pengalaman Pembelajaran CPOB/GMP', 'description' => 'Mengukur pengalaman awal mahasiswa terkait pembelajaran CPOB/GMP.'],
            ['name' => 'Kesulitan Belajar CPOB/GMP', 'description' => 'Mengukur area konsep dan proses CPOB/GMP yang sulit dipahami.'],
            ['name' => 'Kebutuhan Media VR/PharmVR', 'description' => 'Mengukur kebutuhan terhadap media visual dan simulasi PharmVR.'],
            ['name' => 'Kesiapan Teknologi dan Penerimaan', 'description' => 'Mengukur kesiapan teknologi dan penerimaan penggunaan PharmVR.'],
            ['name' => 'Risiko Kenyamanan Penggunaan VR', 'description' => 'Mencatat risiko kenyamanan penggunaan VR; tidak diagregasi sebagai kesiapan positif.'],
            ['name' => 'Persetujuan dan Kriteria Responden', 'description' => 'Item deskriptif persetujuan dan kriteria responden.'],
            ['name' => 'Profil Responden', 'description' => 'Item deskriptif profil responden.'],
            ['name' => 'Prioritas Scene PharmVR', 'description' => 'Prioritas scene pembelajaran yang dibutuhkan responden.'],
            ['name' => 'Prioritas Fitur PharmVR', 'description' => 'Prioritas fitur aplikasi yang dibutuhkan responden.'],
            ['name' => 'Masukan Terbuka', 'description' => 'Masukan terbuka untuk pengembangan PharmVR.'],
        ];
    }

    private function indicator(Survey $survey, SurveyScale $scale, array $indicator): SurveyIndicator
    {
        return $survey->indicators()->firstOrCreate(
            ['slug' => Str::slug($indicator['name'])],
            [
                'survey_scale_id' => str_contains($indicator['name'], 'Profil')
                    || str_contains($indicator['name'], 'Persetujuan')
                    || str_contains($indicator['name'], 'Prioritas')
                    || str_contains($indicator['name'], 'Masukan')
                    || str_contains($indicator['name'], 'Risiko')
                        ? null
                        : $scale->id,
                'name' => $indicator['name'],
                'description' => $indicator['description'],
                'sort_order' => $survey->indicators()->count() + 1,
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questions(): array
    {
        $likertScale = ['scale' => [1, 2, 3, 4, 5]];
        $likertHelp = 'Pilih jawaban sesuai tingkat persetujuan Anda.';
        $likert = fn (string $key, string $page, string $indicator, string $label): array => [
            'key' => $key,
            'page' => $page,
            'indicator' => $indicator,
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => $label,
            'help' => $likertHelp,
            'settings' => $likertScale,
            'required' => true,
            'score' => true,
        ];

        return [
            ['key' => 'A1', 'page' => 'Persetujuan dan Kriteria Responden', 'indicator' => 'Persetujuan dan Kriteria Responden', 'type' => SurveyQuestion::TYPE_CONSENT, 'label' => 'Saya bersedia menjadi responden penelitian ini.', 'required' => true],
            ['key' => 'A2', 'page' => 'Persetujuan dan Kriteria Responden', 'indicator' => 'Persetujuan dan Kriteria Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Saya adalah mahasiswa farmasi atau pernah mengikuti pembelajaran farmasi industri.', 'options' => ['choices' => ['Ya', 'Tidak']], 'required' => true],
            ['key' => 'A3', 'page' => 'Persetujuan dan Kriteria Responden', 'indicator' => 'Persetujuan dan Kriteria Responden', 'type' => SurveyQuestion::TYPE_CONSENT, 'label' => 'Saya memahami bahwa data digunakan hanya untuk penelitian.', 'required' => true],
            ['key' => 'B1', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SHORT_TEXT, 'label' => 'Program studi atau institusi.', 'required' => false],
            ['key' => 'B2', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Semester saat ini.', 'options' => ['choices' => ['1-2', '3-4', '5-6', '7 atau lebih']], 'required' => false],
            ['key' => 'B3', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Pernah mengikuti mata kuliah farmasi industri.', 'options' => ['choices' => ['Ya', 'Belum']], 'required' => false],
            ['key' => 'B4', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Pernah menggunakan VR untuk pembelajaran.', 'options' => ['choices' => ['Ya', 'Belum']], 'required' => false],
            ['key' => 'B5', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SHORT_TEXT, 'label' => 'Perangkat yang biasa digunakan untuk belajar digital.', 'required' => false],
            $likert('C1', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya telah memperoleh materi dasar mengenai CPOB/GMP.'),
            $likert('C2', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya pernah mempelajari alur produksi obat di industri farmasi.'),
            $likert('C3', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya pernah melihat contoh layout atau ruang produksi farmasi melalui gambar, video, atau media pembelajaran lain.'),
            $likert('C4', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya pernah mengikuti praktikum, kunjungan industri, atau pembelajaran yang berkaitan dengan farmasi industri.'),
            $likert('C5', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya memahami hubungan antara QA, QC, Produksi, Warehouse, PPIC, Purchasing, dan Engineering dalam industri farmasi.'),
            $likert('C6', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya memahami pentingnya dokumentasi dalam proses produksi obat.'),
            $likert('C7', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya memahami konsep line clearance, IPC, batch record, deviation, CAPA, dan release produk secara umum.'),
            $likert('D1', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya mengalami kesulitan membayangkan alur produksi obat secara nyata.'),
            $likert('D2', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya mengalami kesulitan memahami peran antar departemen dalam industri farmasi.'),
            $likert('D3', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya mengalami kesulitan memahami layout ruang produksi sesuai CPOB/GMP.'),
            $likert('D4', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya membutuhkan contoh visual untuk memahami dokumentasi produksi.'),
            $likert('D5', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya membutuhkan simulasi untuk memahami line clearance dan IPC.'),
            $likert('D6', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya membutuhkan media yang menampilkan alur batch record, deviation, CAPA, dan release produk.'),
            $likert('D7', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya merasa pembelajaran saat ini belum cukup menggambarkan kondisi industri farmasi.'),
            $likert('E1', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Media VR dapat membantu saya memahami alur produksi obat.'),
            $likert('E2', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'PharmVR perlu menampilkan layout fasilitas produksi secara visual.'),
            $likert('E3', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'PharmVR perlu menampilkan interaksi antar departemen.'),
            $likert('E4', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'PharmVR perlu menyediakan simulasi line clearance dan IPC.'),
            $likert('E5', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'PharmVR perlu menyediakan contoh dokumentasi produksi.'),
            $likert('E6', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'PharmVR perlu menyediakan umpan balik saat pengguna menyelesaikan aktivitas.'),
            $likert('E7', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'PharmVR berpotensi meningkatkan kesiapan belajar farmasi industri.'),
            $likert('F1', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya tertarik menggunakan PharmVR sebagai media pembelajaran.'),
            $likert('F2', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya merasa mampu mempelajari cara menggunakan PharmVR.'),
            $likert('F3', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya bersedia mencoba pembelajaran menggunakan perangkat VR.'),
            $likert('F4', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya memiliki akses ke perangkat yang mendukung pembelajaran digital.'),
            $likert('F5', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya merasa PharmVR akan berguna untuk memahami CPOB/GMP.'),
            ['key' => 'F6', 'page' => 'Kesiapan Teknologi dan Penerimaan', 'indicator' => 'Risiko Kenyamanan Penggunaan VR', 'type' => SurveyQuestion::TYPE_LIKERT, 'label' => 'Saya khawatir penggunaan VR dapat menimbulkan ketidaknyamanan seperti pusing atau mual.', 'help' => $likertHelp, 'settings' => $likertScale, 'required' => true, 'settings_extra' => ['risk_item' => true, 'not_positive_readiness' => true]],
            $likert('F7', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya membutuhkan panduan singkat sebelum menggunakan PharmVR.'),
            ['key' => 'G1', 'page' => 'Prioritas Scene dan Fitur PharmVR', 'indicator' => 'Prioritas Scene PharmVR', 'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'label' => 'Pilih maksimal 3 scene PharmVR yang paling dibutuhkan.', 'options' => ['choices' => ['Gudang bahan awal', 'Produksi tablet', 'Quality control', 'Quality assurance', 'PPIC dan purchasing', 'Engineering dan maintenance']], 'settings' => ['max_selections' => 3], 'required' => false],
            ['key' => 'G2', 'page' => 'Prioritas Scene dan Fitur PharmVR', 'indicator' => 'Prioritas Fitur PharmVR', 'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'label' => 'Pilih maksimal 3 fitur PharmVR yang paling penting.', 'options' => ['choices' => ['Peta alur proses', 'Simulasi tugas', 'Kuis singkat', 'Umpan balik otomatis', 'Glosarium istilah', 'Catatan dokumentasi']], 'settings' => ['max_selections' => 3], 'required' => false],
            ['key' => 'G3', 'page' => 'Prioritas Scene dan Fitur PharmVR', 'indicator' => 'Prioritas Scene PharmVR', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Scene lain yang menurut Anda perlu ditambahkan.', 'required' => false],
            ['key' => 'G4', 'page' => 'Prioritas Scene dan Fitur PharmVR', 'indicator' => 'Prioritas Fitur PharmVR', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Fitur lain yang menurut Anda perlu ditambahkan.', 'required' => false],
            ['key' => 'G5', 'page' => 'Prioritas Scene dan Fitur PharmVR', 'indicator' => 'Prioritas Fitur PharmVR', 'type' => SurveyQuestion::TYPE_SHORT_TEXT, 'label' => 'Prioritas utama Anda dalam satu kalimat.', 'required' => false],
            ['key' => 'H1', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Apa kendala utama Anda dalam mempelajari CPOB/GMP?', 'required' => false],
            ['key' => 'H2', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Apa harapan Anda terhadap media PharmVR?', 'required' => false],
            ['key' => 'H3', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Bagian proses industri farmasi apa yang paling perlu divisualisasikan?', 'required' => false],
            ['key' => 'H4', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Saran fitur atau pengalaman pengguna untuk PharmVR.', 'required' => false],
            ['key' => 'H5', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Catatan tambahan untuk peneliti.', 'required' => false],
        ];
    }
}
