<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Services\SurveyBuilderReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhUx002InstrumentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_work_areas_reach_existing_destinations_and_preserve_seven_builder_steps(): void
    {
        [$owner, $survey, $project] = $this->surveyFixture();
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Ronde Sintetis',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        $survey->load([
            'pages.questions.scoring.indicator',
            'questions.page',
            'questions.scoring.indicator.scale',
            'scales',
            'indicators.scale',
            'indicators.questionScorings.question',
            'validationRounds.assignments.scores',
            'analysisResults',
            'responses',
        ]);
        $stepsBefore = app(SurveyBuilderReadinessService::class)->build($survey)['steps'];

        $response = $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeInOrder(['Siapkan', 'Tinjau', 'Uji Coba', 'Kumpulkan Data', 'Laporan'])
            ->assertSee('data-workspace-group="siapkan"', false)
            ->assertSee('data-workspace-group="tinjau"', false)
            ->assertSee('data-workspace-group="uji-coba"', false)
            ->assertSee('data-workspace-group="kumpulkan-data"', false)
            ->assertSee('data-workspace-group="laporan"', false)
            ->assertSee(route('filament.admin.resources.surveys.index'), false)
            ->assertSee(route('admin.surveys.supervisor-review.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.validation.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]), false)
            ->assertSee(route('admin.surveys.readability.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.respondent-package.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.preflight.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.distribution.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.collection-monitoring.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.responses.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.analysis.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.analysis-package.index', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.responses.export', ['survey' => $survey]), false);

        $this->assertSame(5, substr_count($response->getContent(), 'data-workspace-group='));
        $this->assertSame(7, substr_count($response->getContent(), 'data-builder-step'));

        $survey->refresh()->load([
            'pages.questions.scoring.indicator',
            'questions.page',
            'questions.scoring.indicator.scale',
            'scales',
            'indicators.scale',
            'indicators.questionScorings.question',
            'validationRounds.assignments.scores',
            'analysisResults',
            'responses',
        ]);
        $this->assertSame($stepsBefore, app(SurveyBuilderReadinessService::class)->build($survey)['steps']);
    }

    public function test_advanced_settings_are_folded_but_reopen_for_bulk_preview_and_old_input(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $builderUrl = route('admin.surveys.builder.index', ['survey' => $survey]);

        $this->actingAs($owner)
            ->get($builderUrl)
            ->assertOk()
            ->assertSee('data-advanced-settings', false)
            ->assertSeeText('Pengaturan lanjutan')
            ->assertSee(route('admin.surveys.builder.bulk-questions.preview', ['survey' => $survey]), false)
            ->assertSee(route('admin.surveys.builder.bulk-questions.import', ['survey' => $survey]), false);

        $response = $this->actingAs($owner)
            ->post(route('admin.surveys.builder.bulk-questions.preview', ['survey' => $survey]), [
                'bulk_input' => "PAGE: Bagian Sintetis\nTYPE: short_text\n\nSYN-01 | Pertanyaan sintetis untuk pratinjau.",
                'indicator_strategy' => 'skip',
            ]);

        $response->assertRedirect($builderUrl);

        $bulkPreviewPage = $this->followRedirects($response)
            ->assertOk()
            ->assertSee('data-advanced-settings', false)
            ->assertSeeText('Pertanyaan sintetis untuk pratinjau.');

        $this->assertMatchesRegularExpression(
            '/<details[^>]*data-advanced-settings[^>]*\sopen(?:\s|>)/',
            $bulkPreviewPage->getContent(),
        );

        $oldInputPage = $this->withSession(['_old_input' => ['options_json' => '{"choices":["A","B"]}']])
            ->actingAs($owner)
            ->get($builderUrl)
            ->assertOk()
            ->assertSee('data-question-advanced', false);

        $this->assertMatchesRegularExpression(
            '/<details[^>]*data-question-advanced[^>]*\sopen(?:\s|>)/',
            $oldInputPage->getContent(),
        );
    }

    public function test_preview_pilot_and_main_response_links_are_labelled_by_their_real_function(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $before = $survey->responses()->count();

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Pratinjau responden khusus admin')
            ->assertSeeText('pratinjau admin tanpa simpan — tidak ada respons yang disimpan.')
            ->assertSeeText('Paket pilot dan responden')
            ->assertSeeText('Tautan responden utama belum tersedia')
            ->assertDontSee('href="'.route('survey.show', ['survey' => $survey->slug]).'"', false);

        $this->assertSame($before, $survey->responses()->count());

        $survey->update(['status' => Survey::STATUS_PUBLISHED, 'is_public' => true]);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Buka tautan responden utama')
            ->assertSee('href="'.route('survey.show', ['survey' => $survey->slug]).'"', false)
            ->assertDontSeeText('Buka pratinjau publik');

        $this->assertSame($before, $survey->responses()->count());
    }

    public function test_workspace_keeps_project_and_survey_context_and_denies_direct_cross_project_access(): void
    {
        [$owner, $survey, $project] = $this->surveyFixture();
        $otherProject = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Proyek Pembanding Rahasia',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $otherSurvey = app(CreateSurveyAction::class)->handle($owner, $otherProject, [
            'title' => 'Instrumen Pembanding Rahasia',
            'description' => 'Tidak boleh tertukar.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText($project->title)
            ->assertSeeText($survey->title)
            ->assertDontSeeText($otherProject->title)
            ->assertDontSeeText($otherSurvey->title);

        auth()->logout();
        $this->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertRedirect('/admin/login');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertForbidden();
    }

    public function test_opening_and_navigating_workspace_does_not_mutate_question_content_or_readiness(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $page = $survey->pages()->create(['title' => 'Bagian Tetap', 'sort_order' => 1]);
        $survey->questions()->create([
            'page_id' => $page->id,
            'question_key' => 'item_tetap',
            'type' => SurveyQuestion::TYPE_SINGLE_CHOICE,
            'label' => 'Redaksi harus tetap sama.',
            'options' => ['choices' => ['Pilihan A', 'Pilihan B']],
            'settings' => ['routing' => ['Pilihan A' => 'continue']],
            'is_required' => true,
            'sort_order' => 17,
        ]);
        $snapshot = $survey->questions()->orderBy('sort_order')->get([
            'question_key', 'label', 'type', 'options', 'settings', 'is_required', 'sort_order',
        ])->map->toArray()->all();

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Redaksi harus tetap sama.');

        $after = $survey->questions()->orderBy('sort_order')->get([
            'question_key', 'label', 'type', 'options', 'settings', 'is_required', 'sort_order',
        ])->map->toArray()->all();

        $this->assertSame($snapshot, $after);
    }

    public function test_responsive_and_keyboard_contract_is_present_without_global_fixed_width_navigation(): void
    {
        [$owner, $survey] = $this->surveyFixture('Instrumen dengan Judul Panjang untuk Memastikan Pembungkus Teks Tetap Dapat Dibaca di Lebar 320 Piksel');

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('sm:grid-cols-2 xl:grid-cols-5', false)
            ->assertSee('break-words', false)
            ->assertSee('overflow-x-auto', false)
            ->assertSee('focus-visible:outline', false)
            ->assertSee('data-unsaved-warning', false);
    }

    /**
     * @return array{0: User, 1: Survey, 2: ResearchProject}
     */
    private function surveyFixture(string $title = 'Instrumen Sintetis RH-UX-002'): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Proyek Sintetis RH-UX-002',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => $title,
            'description' => 'Deskripsi instrumen sintetis.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
        ]);

        return [$owner, $survey, $project];
    }
}
