<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\SupervisionReviewLink;
use App\Models\Survey;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use Database\Seeders\MyRisetDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicOutputBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_output_blocks_render_across_admin_workflows_without_sensitive_data(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $admin = User::query()->where('email', 'admin@researchhub.test')->firstOrFail();
        $project = ResearchProject::query()->where('slug', 'disertasi-pharmvr')->firstOrFail();
        $survey = Survey::query()->where('slug', 'angket-evaluasi-pembelajaran-pharmvr')->firstOrFail();
        $round = SurveyValidationRound::query()->where('title', 'Validasi Instrumen Angket Evaluasi PharmVR')->firstOrFail();

        $builder = $this->actingAs($admin)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Survey Instrument Summary')
            ->assertSeeText('Expert Validation Summary')
            ->assertSeeText('Survey Response / Analysis Summary')
            ->assertSeeText('Salin Narasi')
            ->assertSeeText('Narasi akademik non-AI');

        $this->assertSafeAcademicOutputResponse($builder);

        $validation = $this->actingAs($admin)
            ->get(route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSeeText('Expert Validation Summary')
            ->assertSeeText('Aiken/CVI Interpretation')
            ->assertSeeText('Copy-Ready Narrative')
            ->assertSeeText('alat bantu keputusan');

        $this->assertSafeAcademicOutputResponse($validation);

        $supervision = $this->actingAs($admin)
            ->get(route('admin.projects.supervision.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSeeText('Follow-Up Revision Summary')
            ->assertSeeText('Supervision Summary')
            ->assertSeeText('Sumber: Supervision Session');

        $this->assertSafeAcademicOutputResponse($supervision);

        $journey = $this->actingAs($admin)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertOk()
            ->assertSeeText('Project Progress Summary')
            ->assertSeeText('Follow-Up Revision Summary')
            ->assertSeeText('Alur riset project');

        $this->assertSafeAcademicOutputResponse($journey);
    }

    public function test_academic_output_blocks_respect_project_authorization(): void
    {
        $this->seed(MyRisetDemoSeeder::class);

        $outsider = User::factory()->create();
        $outsider->assignRole('admin');
        $project = ResearchProject::query()->where('slug', 'disertasi-pharmvr')->firstOrFail();
        $survey = Survey::query()->where('slug', 'angket-evaluasi-pembelajaran-pharmvr')->firstOrFail();

        $this->actingAs($outsider)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertForbidden();
    }

    private function assertSafeAcademicOutputResponse(mixed $response): void
    {
        $response
            ->assertDontSee('token_hash')
            ->assertDontSee(SurveyValidationAssignment::hashToken('raw-validation-dashboard-token'))
            ->assertDontSee(SupervisionReviewLink::hashToken('raw-supervision-dashboard-token'))
            ->assertDontSee('/validation/survey/')
            ->assertDontSee('/supervision/review/')
            ->assertDontSee('validator.materi@example.test')
            ->assertDontSee('validator.media@example.test')
            ->assertDontSee('validator.metode@example.test')
            ->assertDontSee('MR-DEMO-001')
            ->assertDontSee('response_token_hash');
    }
}
