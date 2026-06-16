<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\Surveys\Support\SurveyIntroTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreatePractitionerInterviewFormAction
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
            ->where('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $mainSurvey, $groupKey, $request): Survey {
            $mainSurveyUpdates = [
                'analysis_group_key' => $groupKey,
                'instrument_type' => $mainSurvey->instrument_type ?: Survey::INSTRUMENT_ANALYSIS_STUDENT,
            ];

            foreach (SurveyIntroTemplates::studentPharmVr() as $key => $value) {
                if ($key === 'require_consent_before_start') {
                    $mainSurveyUpdates[$key] = $mainSurvey->require_consent_before_start ?: $value;

                    continue;
                }

                $mainSurveyUpdates[$key] = filled($mainSurvey->{$key}) ? $mainSurvey->{$key} : $value;
            }

            $mainSurveyNeedsUpdate = false;
            foreach ($mainSurveyUpdates as $key => $value) {
                if ($mainSurvey->{$key} !== $value) {
                    $mainSurveyNeedsUpdate = true;

                    break;
                }
            }

            if ($mainSurveyNeedsUpdate) {
                $mainSurvey->forceFill($mainSurveyUpdates)->save();
            }

            $survey = $this->createSurvey->handle($user, $mainSurvey->project, [
                'title' => 'Pedoman Wawancara Praktisi/Ahli CPOB PharmVR',
                'description' => 'Instrumen ini digunakan untuk merekap wawancara praktisi industri, QA, QC, produksi, validasi, atau regulatory terkait akurasi konten CPOB/GMP, prioritas scene, risiko miskonsepsi, dan kebutuhan industri. Responden dapat menggunakan inisial dan dapat mengosongkan nama perusahaan jika bersifat rahasia.',
                'identity_mode' => Survey::IDENTITY_HIDDEN,
                'instrument_type' => Survey::INSTRUMENT_PRACTITIONER_INTERVIEW,
                'parent_survey_id' => $mainSurvey->getKey(),
                'analysis_group_key' => $groupKey,
                ...SurveyIntroTemplates::practitionerPharmVr(),
            ], $request);

            $this->buildTemplate($user, $survey, $request);

            return $survey->load(['pages.questions', 'questions']);
        });
    }

    private function buildTemplate(User $user, Survey $survey, ?Request $request): void
    {
        $sections = [
            ['SECTION A - Identitas Narasumber', [
                ['practitioner_name_initial', SurveyQuestion::TYPE_SHORT_TEXT, 'Nama / Inisial Narasumber', null],
                ['practitioner_institution', SurveyQuestion::TYPE_SHORT_TEXT, 'Institusi / Industri (boleh dikosongkan bila rahasia)', null],
                ['practitioner_position', SurveyQuestion::TYPE_SHORT_TEXT, 'Jabatan / Bidang', null],
                ['practitioner_expertise_area', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Area keahlian', ['QA', 'QC', 'Produksi', 'Validasi', 'Regulatory', 'Warehouse', 'Engineering', 'PPIC', 'Purchasing', 'Akademisi CPOB', 'Lainnya']],
                ['practitioner_industry_experience', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Lama pengalaman di industri farmasi', ['Kurang dari 2 tahun', '2-5 tahun', '6-10 tahun', 'Lebih dari 10 tahun']],
            ]],
            ['SECTION B - Pertanyaan Wawancara Inti', [
                ['interview_core_cpob_aspects', SurveyQuestion::TYPE_LONG_TEXT, 'Aspek CPOB/GMP apa yang paling penting dipahami mahasiswa farmasi sebelum memasuki dunia industri?', null],
                ['interview_difficult_industry_process', SurveyQuestion::TYPE_LONG_TEXT, 'Bagian proses industri farmasi apa yang biasanya sulit dipahami mahasiswa atau fresh graduate?', null],
                ['interview_vr_relevance', SurveyQuestion::TYPE_LONG_TEXT, 'Apakah simulasi VR relevan untuk membantu mahasiswa memahami CPOB/GMP? Mohon jelaskan.', null],
                ['interview_priority_scene', SurveyQuestion::TYPE_LONG_TEXT, 'Scene apa yang paling penting dikembangkan terlebih dahulu dalam PharmVR?', null],
                ['interview_initial_scene_accuracy', SurveyQuestion::TYPE_LONG_TEXT, 'Apakah Hygiene, Gowning, Airlock, Production Corridor, dan Weighing tepat sebagai prioritas awal?', null],
                ['interview_hygiene_gowning_errors', SurveyQuestion::TYPE_LONG_TEXT, 'Kesalahan apa yang perlu ditampilkan dalam simulasi hygiene dan gowning sebagai pembelajaran?', null],
                ['interview_airlock_corridor_aspects', SurveyQuestion::TYPE_LONG_TEXT, 'Aspek apa yang harus diperhatikan dalam simulasi airlock dan production corridor?', null],
                ['interview_weighing_requirements', SurveyQuestion::TYPE_LONG_TEXT, 'Aspek apa yang wajib ada dalam simulasi weighing agar sesuai CPOB/GMP?', null],
                ['interview_documentation_need', SurveyQuestion::TYPE_LONG_TEXT, 'Apakah dokumentasi seperti batch record, logbook, status label, dan deviation perlu ditambahkan?', null],
                ['interview_vr_assessment', SurveyQuestion::TYPE_LONG_TEXT, 'Bentuk evaluasi apa yang sesuai untuk menilai pemahaman mahasiswa dalam pembelajaran CPOB/GMP berbasis VR?', null],
                ['interview_misconception_risk', SurveyQuestion::TYPE_LONG_TEXT, 'Apa risiko jika simulasi VR CPOB/GMP dibuat tidak sesuai praktik industri?', null],
                ['interview_pharmvr_validity_suggestion', SurveyQuestion::TYPE_LONG_TEXT, 'Apa saran agar PharmVR valid, relevan, dan mendekati kebutuhan industri farmasi?', null],
                ['interview_validator_willingness', SurveyQuestion::TYPE_LONG_TEXT, 'Apakah Bapak/Ibu bersedia menjadi validator ahli atau memberikan masukan lanjutan?', null],
            ]],
            ['SECTION C - Koding Tema Wawancara', [
                ['interview_theme_codes', SurveyQuestion::TYPE_MULTIPLE_CHOICE, 'Tema utama hasil wawancara', ['Kebutuhan konten CPOB', 'Alur produksi', 'Risiko miskonsepsi', 'Akurasi scene', 'Dokumentasi', 'QA/QC', 'Hygiene dan Gowning', 'Airlock dan fasilitas', 'Weighing', 'Assessment', 'Implementasi teknologi', 'Saran validasi']],
                ['interview_priority_level', SurveyQuestion::TYPE_SINGLE_CHOICE, 'Prioritas hasil wawancara', ['Rendah', 'Sedang', 'Tinggi', 'Sangat tinggi']],
                ['interview_design_implication', SurveyQuestion::TYPE_LONG_TEXT, 'Implikasi terhadap desain PharmVR', null],
                ['interview_development_implication', SurveyQuestion::TYPE_LONG_TEXT, 'Implikasi terhadap development PharmVR', null],
            ]],
        ];

        foreach ($sections as $pageIndex => [$title, $questions]) {
            $page = $this->createPage->handle($user, $survey, [
                'title' => $title,
                'description' => $pageIndex === 1 ? 'Form ini dapat diisi peneliti sebagai catatan wawancara terstruktur atau dibagikan sebagai form publik kepada praktisi.' : null,
                'sort_order' => $pageIndex + 1,
            ], $request);

            foreach ($questions as $questionIndex => [$key, $type, $label, $choices]) {
                $this->createQuestion->handle($user, $survey, [
                    'page_id' => $page->getKey(),
                    'question_key' => $key,
                    'type' => $type,
                    'label' => $label,
                    'help_text' => str_contains($key, 'institution') ? 'Boleh dikosongkan jika nama institusi/perusahaan bersifat rahasia.' : null,
                    'options_json' => $choices ? json_encode(['choices' => $choices], JSON_THROW_ON_ERROR) : null,
                    'settings_json' => null,
                    'is_required' => false,
                    'sort_order' => $questionIndex + 1,
                ], $request);
            }
        }
    }
}
