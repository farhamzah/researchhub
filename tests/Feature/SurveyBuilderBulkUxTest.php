<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AcademicOutputs\Services\AcademicNarrativeService;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyBuilderBulkUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_survey_instrument_summary_override_is_used_and_can_be_cleared(): void
    {
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.instrument-summary.update', ['survey' => $survey]), [
                'summary_action' => 'use_manual',
                'instrument_summary_override' => 'Ringkasan manual instrumen PharmVR untuk proposal.',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $survey->refresh();

        $this->assertSame('Ringkasan manual instrumen PharmVR untuk proposal.', $survey->instrument_summary_override);
        $this->assertSame('Ringkasan manual instrumen PharmVR untuk proposal.', app(AcademicNarrativeService::class)->surveyInstrumentSummary($survey));

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Manual summary active')
            ->assertSeeText('Ringkasan manual instrumen PharmVR untuk proposal.');

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.instrument-summary.update', ['survey' => $survey]), [
                'summary_action' => 'clear_manual',
                'instrument_summary_override' => 'Ignored on clear.',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->assertNull($survey->refresh()->instrument_summary_override);
    }

    public function test_indicator_description_can_be_created_updated_and_displayed(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->post(route('admin.surveys.scoring.indicators.store', ['survey' => $survey]), [
                'name' => 'Kebutuhan Media VR/PharmVR',
                'description' => 'Mengukur kebutuhan terhadap media visual dan simulasi.',
            ])
            ->assertRedirect(route('admin.surveys.scoring.index', ['survey' => $survey]));

        $indicator = $survey->indicators()->firstOrFail();

        $this->actingAs($owner)
            ->put(route('admin.surveys.scoring.indicators.update', ['survey' => $survey, 'indicator' => $indicator]), [
                'name' => $indicator->name,
                'description' => 'Deskripsi indikator yang diperbarui.',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.surveys.scoring.index', ['survey' => $survey]));

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Deskripsi indikator yang diperbarui.');
    }

    public function test_bulk_text_import_creates_page_questions_indicator_links_and_scoring(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $indicator = $survey->indicators()->create([
            'name' => 'Pengalaman Pembelajaran CPOB/GMP',
            'slug' => 'pengalaman-pembelajaran-cpob-gmp',
        ]);

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.bulk-questions.preview', ['survey' => $survey]), [
                'indicator_strategy' => 'cancel',
                'bulk_input' => $this->bulkText(),
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHas('bulk_question_preview');

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.bulk-questions.import', ['survey' => $survey]), [
                'indicator_strategy' => 'cancel',
                'bulk_input' => $this->bulkText(),
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_pages', [
            'survey_id' => $survey->id,
            'title' => 'Pengalaman Pembelajaran CPOB/GMP',
        ]);
        $this->assertDatabaseHas('survey_questions', [
            'survey_id' => $survey->id,
            'question_key' => 'C1',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'is_required' => true,
        ]);
        $this->assertSame(2, $survey->questions()->count());
        $this->assertSame(2, $indicator->questionScorings()->count());
    }

    public function test_bulk_json_import_creates_missing_indicator_when_requested(): void
    {
        [$owner, $survey] = $this->surveyFixture();

        $input = json_encode([
            'page' => ['title' => 'Kesulitan Belajar CPOB/GMP', 'description' => 'Bagian D', 'order' => 4],
            'defaults' => [
                'type' => 'likert',
                'required' => true,
                'indicator' => 'Kesulitan Belajar CPOB/GMP',
                'scale' => [1, 2, 3, 4, 5],
                'min' => 1,
                'max' => 5,
                'weight' => 1,
            ],
            'questions' => [
                ['key' => 'D1', 'text' => 'Saya mengalami kesulitan membayangkan alur produksi obat.'],
                ['key' => 'D2', 'text' => 'Saya mengalami kesulitan memahami layout ruang produksi.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.bulk-questions.import', ['survey' => $survey]), [
                'indicator_strategy' => 'create',
                'bulk_input' => $input,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_indicators', [
            'survey_id' => $survey->id,
            'name' => 'Kesulitan Belajar CPOB/GMP',
        ]);
        $this->assertSame(2, $survey->questions()->count());
        $this->assertSame(2, $survey->questionScorings()->count());
    }

    public function test_bulk_import_rejects_duplicate_keys_and_rolls_back(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $survey->questions()->create([
            'question_key' => 'C1',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Existing C1',
        ]);

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->post(route('admin.surveys.builder.bulk-questions.import', ['survey' => $survey]), [
                'indicator_strategy' => 'create',
                'bulk_input' => $this->bulkText(),
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('bulk_input');

        $this->assertSame(1, $survey->questions()->count());
        $this->assertSame(0, $survey->pages()->count());
    }

    public function test_matrix_labels_render_without_duplication_and_matrix_is_not_missing_scoring(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->surveyFixture();
        $page = $survey->pages()->create(['title' => 'Matrix Page']);
        $matrix = $survey->questions()->create([
            'page_id' => $page->id,
            'question_key' => 'matrix_need',
            'type' => SurveyQuestion::TYPE_LIKERT_MATRIX,
            'label' => 'Matrix needs',
            'options' => [
                'rows' => ['Alur produksi mudah dipahami'],
                'columns' => [
                    ['value' => '1', 'label' => 'Sangat tidak setuju'],
                    ['value' => '2', 'label' => 'Tidak setuju'],
                ],
            ],
            'is_required' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.surveys.scoring.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Likert Matrix is collected for analysis/export but not included in scoring.')
            ->assertSeeText('Not scoreable')
            ->assertSeeText('Convert Matrix');

        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSeeText('1 — Sangat tidak setuju')
            ->assertDontSeeText('11 = Sangat tidak setuju');

        $this->actingAs($owner)
            ->post(route('admin.surveys.scoring.questions.convert-matrix', ['survey' => $survey, 'question' => $matrix]))
            ->assertRedirect(route('admin.surveys.scoring.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_questions', [
            'survey_id' => $survey->id,
            'question_key' => 'matrix_need_row_1',
            'type' => SurveyQuestion::TYPE_LIKERT,
        ]);
    }

    public function test_consent_question_cannot_be_scored_and_template_creates_pharmvr_structure(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $survey] = $this->surveyFixture();
        $consent = $survey->questions()->create([
            'question_key' => 'consent',
            'type' => SurveyQuestion::TYPE_CONSENT,
            'label' => 'Consent',
            'is_required' => true,
        ]);

        $this->actingAs($owner)
            ->put(route('admin.surveys.scoring.questions.update', ['survey' => $survey, 'question' => $consent]), [
                'is_scored' => '1',
            ])
            ->assertSessionHasErrors('is_scored');

        [$templateOwner, $templateSurvey] = $this->surveyFixture('PharmVR Template Survey');
        $this->actingAs($templateOwner)
            ->post(route('admin.surveys.builder.templates.pharmvr-student-needs', ['survey' => $templateSurvey]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $templateSurvey]));

        $this->assertSame(8, $templateSurvey->pages()->count());
        $this->assertSame(10, $templateSurvey->indicators()->count());
        $this->assertDatabaseHas('survey_questions', [
            'survey_id' => $templateSurvey->id,
            'question_key' => 'F6',
            'type' => SurveyQuestion::TYPE_LIKERT,
        ]);
        $this->assertFalse((bool) $templateSurvey->questions()->where('question_key', 'F6')->firstOrFail()->scoring->is_scored);
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function surveyFixture(string $title = 'Bulk UX Survey'): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Bulk UX Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => $title,
            'description' => 'Survey builder UX improvements.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        return [$owner, $survey];
    }

    private function bulkText(): string
    {
        return <<<'TEXT'
PAGE: Pengalaman Pembelajaran CPOB/GMP
PAGE_ORDER: 3
INDICATOR: Pengalaman Pembelajaran CPOB/GMP
TYPE: likert
REQUIRED: true
SCALE: 1,2,3,4,5
HELP: Pilih jawaban sesuai tingkat persetujuan Anda.

C1 | Saya telah memperoleh materi dasar mengenai CPOB/GMP.
C2 | Saya pernah mempelajari alur produksi obat di industri farmasi.
TEXT;
    }
}
