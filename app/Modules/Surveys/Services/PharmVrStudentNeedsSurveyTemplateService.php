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

            $survey->forceFill([
                'privacy_statement' => $this->privacyStatement(),
            ])->save();

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

            $survey->forceFill([
                'privacy_statement' => $this->privacyStatement(),
            ])->save();

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
     * @return array<string, mixed>
     */
    public function previewNormalization(Survey $survey): array
    {
        $questions = collect($this->questions())->keyBy('key');
        $existing = $survey->questions()->whereIn('question_key', $questions->keys()->all())->get()->keyBy('question_key');
        $changes = [];

        foreach ($questions as $key => $expected) {
            $question = $existing->get($key);

            if (! $question instanceof SurveyQuestion) {
                continue;
            }

            $fieldChanges = [];
            foreach ($this->questionUpdatePayload($expected) as $field => $value) {
                if ($question->{$field} != $value) {
                    $fieldChanges[] = $field;
                }
            }

            if ($fieldChanges !== []) {
                $changes[] = [
                    'key' => $key,
                    'label' => $expected['label'],
                    'fields' => $fieldChanges,
                ];
            }
        }

        return [
            'response_count' => $survey->responses()->count(),
            'existing_count' => $existing->count(),
            'missing_keys' => $questions->keys()->diff($existing->keys())->values()->all(),
            'change_count' => count($changes),
            'changes' => $changes,
            'privacy_statement' => $this->privacyStatement(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeExisting(User $user, Survey $survey): array
    {
        if ($survey->responses()->exists()) {
            throw ValidationException::withMessages([
                'template' => 'Normalization is blocked because this survey already has responses.',
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
            $updated = 0;
            $missing = [];

            foreach ($this->questions() as $questionData) {
                $question = $survey->questions()->where('question_key', $questionData['key'])->first();

                if (! $question instanceof SurveyQuestion) {
                    $missing[] = $questionData['key'];

                    continue;
                }

                $question->forceFill($this->questionUpdatePayload($questionData) + [
                    'page_id' => $pages->get($questionData['page'])?->id,
                ])->save();

                $indicator = $indicators->get($questionData['indicator'] ?? '');
                $this->syncScoring($survey, $question, $indicator, $questionData);
                $updated++;
            }

            $survey->forceFill([
                'privacy_statement' => $this->privacyStatement(),
            ])->save();

            $this->activityLogger->log('survey.pharmvr_student_needs_template_normalized', $user, $survey->project, $survey, [
                'survey_id' => $survey->id,
                'question_count' => $updated,
                'missing_keys' => $missing,
            ]);

            return [
                'questions' => $updated,
                'missing_keys' => $missing,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function questionUpdatePayload(array $question): array
    {
        return [
            'type' => $question['type'],
            'label' => $question['label'],
            'help_text' => $question['help'] ?? null,
            'options' => $question['options'] ?? null,
            'settings' => $question['settings'] ?? null,
            'is_required' => (bool) ($question['required'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function syncScoring(Survey $survey, SurveyQuestion $question, ?SurveyIndicator $indicator, array $questionData): void
    {
        if (! isset($questionData['indicator'])) {
            $question->scoring()->delete();

            return;
        }

        $question->scoring()->updateOrCreate(
            ['survey_question_id' => $question->id],
            [
                'survey_id' => $survey->id,
                'survey_indicator_id' => $indicator?->id,
                'is_scored' => (bool) ($questionData['score'] ?? false),
                'score_min' => ($questionData['score'] ?? false) ? 1 : null,
                'score_max' => ($questionData['score'] ?? false) ? 5 : null,
                'weight' => 1,
                'is_reverse_scored' => false,
                'settings' => $questionData['settings_extra'] ?? null,
            ],
        );
    }

    private function privacyStatement(): string
    {
        return 'Data yang dikumpulkan digunakan untuk keperluan penelitian dan pengembangan PharmVR. Nama responden digunakan untuk kebutuhan administrasi, pengecekan data, dan pengelolaan respons. Identitas pribadi tidak akan ditampilkan dalam laporan dan hasil penelitian disajikan secara agregat atau disamarkan.';
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
            ['key' => 'A1', 'page' => 'Persetujuan dan Kriteria Responden', 'indicator' => 'Persetujuan dan Kriteria Responden', 'type' => SurveyQuestion::TYPE_CONSENT, 'label' => 'Saya telah membaca penjelasan mengenai tujuan kuesioner ini dan bersedia mengisi kuesioner secara sukarela.', 'help' => 'Pilih persetujuan ini jika Anda bersedia menjadi responden secara sukarela.', 'required' => true],
            ['key' => 'A2', 'page' => 'Persetujuan dan Kriteria Responden', 'indicator' => 'Persetujuan dan Kriteria Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Apakah Anda merupakan mahasiswa farmasi atau mahasiswa yang sedang/akan mempelajari mata kuliah terkait farmasi industri, CPOB, atau GMP?', 'help' => 'Pertanyaan ini digunakan untuk memastikan kesesuaian responden dengan sasaran penelitian.', 'options' => ['choices' => ['Ya', 'Tidak']], 'required' => true],
            ['key' => 'A3', 'page' => 'Persetujuan dan Kriteria Responden', 'indicator' => 'Persetujuan dan Kriteria Responden', 'type' => SurveyQuestion::TYPE_CONSENT, 'label' => 'Saya memahami bahwa data yang dikumpulkan akan digunakan untuk kebutuhan analisis pengembangan media pembelajaran PharmVR. Identitas responden tidak akan ditampilkan dalam laporan dan hasil penelitian akan disajikan secara agregat atau disamarkan.', 'help' => 'Data yang dikumpulkan hanya digunakan untuk kepentingan penelitian dan analisis pengembangan PharmVR. Identitas pribadi tidak ditampilkan dalam laporan hasil penelitian.', 'required' => true],
            ['key' => 'B1', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SHORT_TEXT, 'label' => 'Nama responden', 'help' => 'Nama digunakan hanya untuk kebutuhan administrasi, pengecekan data, dan pengelolaan respons penelitian. Identitas responden akan disamarkan dalam pelaporan hasil.', 'required' => true],
            ['key' => 'B2', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Program studi Anda saat ini:', 'help' => 'Pilih program studi yang paling sesuai.', 'options' => ['choices' => ['S1 Farmasi', 'Profesi Apoteker', 'D3 Farmasi', 'S2 Farmasi', 'Lainnya']], 'required' => true],
            ['key' => 'B3', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Semester atau tingkat pendidikan Anda saat ini:', 'help' => 'Pilih semester atau tingkat yang paling sesuai dengan kondisi Anda.', 'options' => ['choices' => ['Semester 1-2', 'Semester 3-4', 'Semester 5-6', 'Semester 7-8', 'Profesi/Apoteker', 'Lainnya']], 'required' => true],
            ['key' => 'B4', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SHORT_TEXT, 'label' => 'Perguruan tinggi asal Anda:', 'help' => 'Opsional. Boleh dikosongkan jika tidak ingin menyebutkan nama institusi.', 'required' => false],
            ['key' => 'B5', 'page' => 'Profil Responden', 'indicator' => 'Profil Responden', 'type' => SurveyQuestion::TYPE_SINGLE_CHOICE, 'label' => 'Apakah Anda pernah mempelajari materi CPOB/GMP atau farmasi industri?', 'help' => 'Pertanyaan ini digunakan untuk mengetahui pengalaman awal responden terhadap materi CPOB/GMP atau farmasi industri.', 'options' => ['choices' => ['Sudah', 'Sedang mempelajari', 'Belum']], 'required' => true],
            $likert('C1', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya telah memperoleh materi dasar mengenai CPOB/GMP.'),
            $likert('C2', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya pernah mempelajari alur produksi obat di industri farmasi.'),
            $likert('C3', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya pernah melihat contoh layout atau ruang produksi farmasi melalui gambar, video, atau media pembelajaran lain.'),
            $likert('C4', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya pernah mengikuti praktikum, kunjungan industri, atau pembelajaran yang berkaitan dengan farmasi industri.'),
            $likert('C5', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya memahami hubungan antara QA, QC, Produksi, Warehouse, PPIC, Purchasing, dan Engineering dalam industri farmasi.'),
            $likert('C6', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya memahami pentingnya dokumentasi dalam proses produksi obat.'),
            $likert('C7', 'Pengalaman Pembelajaran CPOB/GMP', 'Pengalaman Pembelajaran CPOB/GMP', 'Saya memahami konsep line clearance, IPC, batch record, deviation, CAPA, dan release produk secara umum.'),
            $likert('D1', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya merasa sulit membayangkan bentuk ruang produksi farmasi hanya dari penjelasan teori.'),
            $likert('D2', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya merasa sulit memahami alur personel dan alur material dalam pabrik farmasi.'),
            $likert('D3', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya merasa sulit memahami fungsi airlock, pressure cascade, dan clean corridor.'),
            $likert('D4', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya merasa sulit memahami proses produksi tablet dari penimbangan hingga pengemasan.'),
            $likert('D5', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya merasa sulit memahami hubungan antara proses produksi, QC, QA, dan dokumentasi.'),
            $likert('D6', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Saya membutuhkan media yang lebih visual dan interaktif untuk memahami CPOB/GMP.'),
            $likert('D7', 'Kesulitan Belajar CPOB/GMP', 'Kesulitan Belajar CPOB/GMP', 'Pembelajaran CPOB/GMP akan lebih mudah dipahami jika disajikan dalam simulasi lingkungan industri.'),
            $likert('E1', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Media Virtual Reality dapat membantu saya memahami lingkungan industri farmasi secara lebih konkret.'),
            $likert('E2', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Simulasi VR dapat membantu saya memahami tahapan masuk area produksi seperti hygiene, gowning, dan airlock.'),
            $likert('E3', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Simulasi VR dapat membantu saya memahami alur produksi tablet non-steril.'),
            $likert('E4', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Simulasi VR dapat membantu saya memahami peran QA, QC, Warehouse, PPIC, Purchasing, dan Engineering.'),
            $likert('E5', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Simulasi VR perlu dilengkapi dengan instruksi, checklist, dan feedback saat pengguna melakukan kesalahan.'),
            $likert('E6', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Simulasi VR perlu dilengkapi dengan pretest dan posttest untuk mengukur pemahaman.'),
            $likert('E7', 'Kebutuhan Media VR/PharmVR', 'Kebutuhan Media VR/PharmVR', 'Simulasi VR perlu memiliki dashboard progress agar mahasiswa dapat melihat capaian belajarnya.'),
            $likert('F1', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya tertarik menggunakan media pembelajaran berbasis Virtual Reality.'),
            $likert('F2', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya merasa mampu mempelajari penggunaan aplikasi VR dengan panduan yang jelas.'),
            $likert('F3', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya bersedia menggunakan laptop/komputer untuk mengakses simulasi PharmVR.'),
            $likert('F4', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya bersedia menggunakan smartphone/cardboard jika tersedia.'),
            $likert('F5', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya bersedia menggunakan headset VR jika tersedia di kampus/laboratorium.'),
            ['key' => 'F6', 'page' => 'Kesiapan Teknologi dan Penerimaan', 'indicator' => 'Risiko Kenyamanan Penggunaan VR', 'type' => SurveyQuestion::TYPE_LIKERT, 'label' => 'Saya memiliki kekhawatiran mengalami pusing, mual, atau lelah saat menggunakan VR.', 'help' => $likertHelp, 'settings' => $likertScale, 'required' => true, 'settings_extra' => ['risk_item' => true, 'not_positive_readiness' => true]],
            $likert('F7', 'Kesiapan Teknologi dan Penerimaan', 'Kesiapan Teknologi dan Penerimaan', 'Saya lebih nyaman jika PharmVR tetap menyediakan alternatif akses melalui laptop atau mobile selain headset VR.'),
            ['key' => 'G1', 'page' => 'Prioritas Scene dan Fitur PharmVR', 'indicator' => 'Prioritas Scene PharmVR', 'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'label' => 'Pilih maksimal 3 scene PharmVR yang menurut Anda paling penting untuk dikembangkan terlebih dahulu.', 'help' => 'Pilih maksimal 3 scene yang menurut Anda paling prioritas.', 'options' => ['choices' => ['Hygiene', 'Gowning', 'Airlock', 'Production Corridor', 'Weighing', 'Granulation', 'Final Mixing', 'Tabletting', 'Coating', 'Blistering / Primary Packaging', 'Secondary Packing', 'QC Lab', 'QA Office', 'Warehouse', 'PPIC', 'Purchasing', 'Engineering']], 'settings' => ['max_selections' => 3], 'required' => true],
            ['key' => 'G2', 'page' => 'Prioritas Scene dan Fitur PharmVR', 'indicator' => 'Prioritas Fitur PharmVR', 'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'label' => 'Pilih maksimal 3 fitur yang menurut Anda paling penting dalam PharmVR.', 'help' => 'Pilih maksimal 3 fitur yang menurut Anda paling prioritas.', 'options' => ['choices' => ['Penjelasan dari avatar trainer Vira', 'Panel SOP/CPOB interaktif', 'Checklist langkah kerja', 'Simulasi kesalahan dan feedback', 'Pretest dan posttest', 'Progress belajar dan sertifikat', 'Denah pabrik interaktif', 'Video/materi pendukung', 'Dashboard hasil belajar', 'Mode akses laptop/mobile/headset VR', 'Knowledge hotspot pada alat dan ruangan']], 'settings' => ['max_selections' => 3], 'required' => true],
            ['key' => 'H1', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Materi CPOB/GMP apa yang menurut Anda paling sulit dipahami?', 'required' => false],
            ['key' => 'H2', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Scene atau ruang industri farmasi apa yang paling ingin Anda lihat dalam PharmVR?', 'required' => false],
            ['key' => 'H3', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Fitur apa yang menurut Anda perlu ada agar PharmVR mudah digunakan?', 'required' => false],
            ['key' => 'H4', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Kekhawatiran apa yang Anda miliki jika pembelajaran menggunakan VR?', 'required' => false],
            ['key' => 'H5', 'page' => 'Masukan Terbuka', 'indicator' => 'Masukan Terbuka', 'type' => SurveyQuestion::TYPE_LONG_TEXT, 'label' => 'Saran lain untuk pengembangan PharmVR.', 'required' => false],
        ];
    }
}
