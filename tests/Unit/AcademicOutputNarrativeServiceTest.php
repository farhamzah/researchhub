<?php

namespace Tests\Unit;

use App\Models\ResearchProject;
use App\Models\SupervisionSession;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\AcademicOutputs\Services\AcademicNarrativeService;
use Database\Seeders\MyRisetDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicOutputNarrativeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_output_service_returns_cautious_fallbacks_for_incomplete_data(): void
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Incomplete Academic Output Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Survey Belum Lengkap',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Validasi Belum Lengkap',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 4,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        $session = SupervisionSession::create([
            'research_project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Bimbingan Awal',
            'status' => SupervisionSession::STATUS_DRAFT,
        ]);

        $service = app(AcademicNarrativeService::class);

        $this->assertStringContainsString('belum memiliki butir pertanyaan', $service->surveyInstrumentSummary($survey));
        $this->assertStringContainsString('belum ada validator', $service->expertValidationSummary($round));
        $this->assertStringContainsString('belum tersedia', $service->validityInterpretation($round));
        $this->assertStringContainsString('belum ada respons terkirim', $service->surveyAnalysisSummary($survey));
        $this->assertStringContainsString('Belum ada tindak lanjut revisi', $service->followUpSummary($project));
        $this->assertStringContainsString('belum memiliki dokumen akademik', $service->documentProgressSummary($project));
        $this->assertStringContainsString('Bimbingan Awal', $service->supervisionSummary($session));
        $this->assertStringContainsString('Alur riset project', $service->projectProgressSummary($project));
    }

    public function test_academic_output_service_uses_structured_data_without_sensitive_leakage(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $project = ResearchProject::query()->where('slug', 'disertasi-pharmvr')->firstOrFail();
        $survey = Survey::query()->where('slug', 'angket-evaluasi-pembelajaran-pharmvr')->firstOrFail();
        $round = SurveyValidationRound::query()->where('title', 'Validasi Instrumen Angket Evaluasi PharmVR')->firstOrFail();
        $session = SupervisionSession::query()->where('title', 'Bimbingan Proposal dan Validasi Instrumen PharmVR')->firstOrFail();
        $service = app(AcademicNarrativeService::class);

        $narratives = [
            $service->surveyInstrumentSummary($survey),
            $service->expertValidationSummary($round),
            $service->validityInterpretation($round),
            $service->surveyAnalysisSummary($survey),
            $service->supervisionSummary($session),
            $service->followUpSummary($project),
            $service->documentProgressSummary($project),
            $service->projectProgressSummary($project),
        ];

        $combined = implode("\n", $narratives);

        $this->assertStringContainsString('Angket Evaluasi Pembelajaran PharmVR', $combined);
        $this->assertStringContainsString("Aiken's V", $combined);
        $this->assertStringContainsString('Disertasi PharmVR', $combined);
        $this->assertStringContainsString('BAB III Metodologi Penelitian', $combined);
        $this->assertStringNotContainsString('token_hash', $combined);
        $this->assertStringNotContainsString('/validation/survey/', $combined);
        $this->assertStringNotContainsString('/supervision/review/', $combined);
        $this->assertStringNotContainsString('validator.materi@example.test', $combined);
        $this->assertStringNotContainsString('MR-DEMO-001', $combined);
    }

    public function test_instrument_summary_ignores_hidden_questions(): void
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Hidden Question Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = Survey::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Hidden Safe Survey',
        ]);
        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'hidden_identity',
            'type' => SurveyQuestion::TYPE_HIDDEN,
            'label' => 'Respondent private identifier',
            'sort_order' => 1,
        ]);
        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'visible_item',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Visible academic item',
            'sort_order' => 2,
        ]);

        $summary = app(AcademicNarrativeService::class)->surveyInstrumentSummary($survey);

        $this->assertStringContainsString('1 butir pertanyaan', $summary);
        $this->assertStringNotContainsString('Respondent private identifier', $summary);
    }
}
