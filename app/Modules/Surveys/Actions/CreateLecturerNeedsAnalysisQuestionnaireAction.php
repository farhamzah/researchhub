<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateLecturerNeedsAnalysisQuestionnaireAction
{
    public function __construct(
        private readonly CreateSurveyAction $createSurvey,
        private readonly CreateSurveyPageAction $createPage,
        private readonly CreateSurveyQuestionAction $createQuestion,
    ) {}

    public function handle(User $user, Survey $mainSurvey, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('runAnalysis', $mainSurvey);
        $mainSurvey->loadMissing('project');

        $groupKey = $mainSurvey->analysis_group_key ?: Survey::ANALYSIS_GROUP_PHARMVR_ADDIE;
        $existing = Survey::query()
            ->where('project_id', $mainSurvey->project_id)
            ->where('parent_survey_id', $mainSurvey->getKey())
            ->where('analysis_group_key', $groupKey)
            ->where('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $mainSurvey, $groupKey, $request): Survey {
            if ($mainSurvey->analysis_group_key !== $groupKey || $mainSurvey->instrument_type === null) {
                $mainSurvey->forceFill([
                    'analysis_group_key' => $groupKey,
                    'instrument_type' => $mainSurvey->instrument_type ?: Survey::INSTRUMENT_ANALYSIS_STUDENT,
                ])->save();
            }

            $survey = $this->createSurvey->handle($user, $mainSurvey->project, [
                'title' => 'Kuesioner Analisis Kebutuhan Dosen PharmVR',
                'description' => 'Instrumen ini digunakan untuk menggali kebutuhan pembelajaran, kesesuaian CPL/CPMK/OBE, prioritas konten CPOB/GMP, assessment, monitoring, dan implementasi PharmVR menurut dosen Farmasi Industri/CPOB.',
                'identity_mode' => Survey::IDENTITY_HIDDEN,
                'instrument_type' => Survey::INSTRUMENT_ANALYSIS_LECTURER,
                'parent_survey_id' => $mainSurvey->getKey(),
                'analysis_group_key' => $groupKey,
            ], $request);

            $this->buildTemplate($user, $survey, $request);

            return $survey->load(['pages.questions', 'questions']);
        });
    }

    private function buildTemplate(User $user, Survey $survey, ?Request $request): void
    {
        $sections = [
            ['SECTION A - Identitas Dosen', [
                ['nama_inisial_dosen', SurveyQuestion::TYPE_SHORT_TEXT, 'Nama / Inisial', null],
                ['institusi_dosen', SurveyQuestion::TYPE_SHORT_TEXT, 'Institusi', null],
                ['bidang_keahlian_dosen', SurveyQuestion::TYPE_SHORT_TEXT, 'Bidang keahlian', null],
                ['lama_pengalaman_mengajar', SurveyQuestion::TYPE_NUMBER, 'Lama pengalaman mengajar', null],
                ['pengalaman_mengajar_cpob', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Pengalaman mengajar Farmasi Industri/CPOB/GMP', ['Belum pernah', '1-2 tahun', '3-5 tahun', 'Lebih dari 5 tahun']],
            ]],
            ['SECTION B - Kebutuhan Pembelajaran CPOB/GMP', $this->likertQuestions([
                'CPOB/GMP perlu diajarkan secara kontekstual.',
                'Pembelajaran CPOB/GMP membutuhkan pemahaman layout, fasilitas, alat, personel, dokumentasi, dan sistem mutu.',
                'Mahasiswa sering mengalami kesulitan membayangkan kondisi industri farmasi nyata.',
                'Media pembelajaran konvensional belum cukup untuk menggambarkan kompleksitas CPOB/GMP.',
                'Akses mahasiswa ke fasilitas industri farmasi nyata masih terbatas.',
                'Mahasiswa membutuhkan pengalaman belajar yang aman dan menyerupai kondisi industri.',
                'Evaluasi pembelajaran CPOB/GMP perlu mencakup aspek kognitif, afektif, dan prosedural.',
            ], 'lecturer_learning_need')],
            ['SECTION C - Kebutuhan Konten PharmVR', $this->likertQuestions([
                'Sistem mutu industri farmasi perlu dimasukkan dalam PharmVR.',
                'Personalia, hygiene, dan gowning perlu menjadi konten utama.',
                'Bangunan, fasilitas, cleanroom, airlock, HVAC, dan pressure cascade perlu divisualisasikan.',
                'Peralatan, status alat, kalibrasi, cleaning status, dan logbook perlu ditampilkan.',
                'Produksi tablet non-steril perlu menjadi alur utama PharmVR.',
                'Weighing, IPC, QC, QA, batch record, deviation, CAPA, dan product release perlu dikenalkan.',
                'Warehouse, FEFO/FIFO, quarantine, released, dan rejected area perlu dimasukkan.',
                'Kualifikasi, validasi, dan manajemen risiko mutu perlu dikenalkan sesuai kebutuhan mahasiswa.',
            ], 'lecturer_content_need')],
            ['SECTION D - Kesesuaian Kurikulum, CPL, CPMK, dan OBE', $this->likertQuestions([
                'PharmVR relevan dengan mata kuliah Farmasi Industri.',
                'PharmVR dapat mendukung pencapaian CPL program studi farmasi.',
                'PharmVR dapat mendukung pencapaian CPMK/Sub-CPMK.',
                'PharmVR sesuai dengan pendekatan Outcome Based Education.',
                'PharmVR mendukung pembelajaran berpusat pada mahasiswa.',
                'Aktivitas PharmVR perlu dipetakan ke CPMK/Sub-CPMK.',
                'Assessment PharmVR perlu selaras dengan capaian pembelajaran.',
                'Tracking aktivitas mahasiswa dapat menjadi bukti proses belajar.',
            ], 'lecturer_obe_alignment')],
            ['SECTION E - Assessment dan Monitoring', $this->likertQuestions([
                'Pretest dan posttest perlu tersedia pada setiap modul/scene.',
                'Bank soal dan randomisasi soal diperlukan.',
                'Rubrik observasi prosedural diperlukan untuk menilai aktivitas mahasiswa.',
                'Tracking durasi dan kesalahan mahasiswa perlu dicatat.',
                'Hint log perlu dicatat sebagai bagian dari evaluasi proses belajar.',
                'Dashboard monitoring dosen diperlukan.',
                'Progress mahasiswa perlu ditampilkan secara terstruktur.',
                'Data tracking dapat digunakan untuk evaluasi proses belajar.',
            ], 'lecturer_assessment_need')],
            ['SECTION F - Teknologi dan Implementasi', $this->likertQuestions([
                'PharmVR perlu mendukung headset VR.',
                'PharmVR perlu mendukung laptop/komputer.',
                'PharmVR perlu mendukung smartphone/cardboard.',
                'Tampilan aplikasi perlu sederhana dan mudah digunakan.',
                'Instruksi penggunaan harus jelas dan bertahap.',
                'Avatar/virtual trainer dapat membantu mahasiswa memahami alur.',
                'Instructor mode diperlukan untuk dosen.',
                'Panduan penggunaan PharmVR perlu disediakan.',
                'Uji coba sistem perlu dilakukan sebelum penelitian utama.',
            ], 'lecturer_technology_need')],
            ['SECTION G - Prioritas Scene PharmVR', [
                ['lecturer_priority_scenes', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Prioritas Scene PharmVR', ['Lobby', 'Training Room', 'Hygiene', 'Gowning', 'Airlock', 'Production Corridor', 'Weighing', 'Granulation', 'Final Mixing', 'Tabletting', 'Coating', 'Blistering / Primary Packaging', 'Secondary Packing', 'QC Lab', 'QA Office', 'Warehouse', 'PPIC', 'Purchasing', 'Engineering']],
            ]],
            ['SECTION H - Pertanyaan Terbuka', [
                ['lecturer_hardest_cpob_material', SurveyQuestion::TYPE_LONG_TEXT, 'Materi CPOB/GMP apa yang paling sulit diajarkan kepada mahasiswa?', null],
                ['lecturer_current_media_weakness', SurveyQuestion::TYPE_LONG_TEXT, 'Apa kelemahan utama media pembelajaran yang saat ini digunakan?', null],
                ['lecturer_vr_relevance', SurveyQuestion::TYPE_LONG_TEXT, 'Menurut Bapak/Ibu, seberapa relevan VR untuk pembelajaran Farmasi Industri/CPOB?', null],
                ['lecturer_first_scene_suggestion', SurveyQuestion::TYPE_LONG_TEXT, 'Scene apa yang paling penting dikembangkan terlebih dahulu dalam PharmVR?', null],
                ['lecturer_pharmvr_feasibility_suggestion', SurveyQuestion::TYPE_LONG_TEXT, 'Apa saran Bapak/Ibu agar PharmVR layak digunakan dalam pembelajaran?', null],
                ['lecturer_vr_assessment_suggestion', SurveyQuestion::TYPE_LONG_TEXT, 'Bentuk evaluasi apa yang paling tepat untuk menilai pembelajaran CPOB/GMP berbasis VR?', null],
            ]],
        ];

        $this->createSections($user, $survey, $sections, $request);
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, array{0: string, 1: string, 2: string, 3: array<int, string>|null}>
     */
    private function likertQuestions(array $labels, string $prefix): array
    {
        return collect($labels)
            ->map(fn (string $label, int $index): array => [
                $prefix.'_'.($index + 1),
                SurveyQuestion::TYPE_LIKERT,
                $label,
                null,
            ])
            ->all();
    }

    /**
     * @param  array<int, array{0: string, 1: array<int, array{0: string, 1: string, 2: string, 3: array<int, string>|null}>}>  $sections
     */
    private function createSections(User $user, Survey $survey, array $sections, ?Request $request): void
    {
        foreach ($sections as $pageIndex => [$title, $questions]) {
            $page = $this->createPage->handle($user, $survey, [
                'title' => $title,
                'sort_order' => $pageIndex + 1,
            ], $request);

            foreach ($questions as $questionIndex => [$key, $type, $label, $choices]) {
                $this->createQuestion->handle($user, $survey, [
                    'page_id' => $page->getKey(),
                    'question_key' => $key ?: Str::slug($label, '_'),
                    'type' => $type,
                    'label' => $label,
                    'help_text' => null,
                    'options_json' => $choices ? json_encode(['choices' => $choices], JSON_THROW_ON_ERROR) : null,
                    'settings_json' => $type === SurveyQuestion::TYPE_LIKERT ? json_encode(['scale' => [1, 2, 3, 4, 5]], JSON_THROW_ON_ERROR) : null,
                    'is_required' => false,
                    'sort_order' => $questionIndex + 1,
                ], $request);
            }
        }
    }
}
