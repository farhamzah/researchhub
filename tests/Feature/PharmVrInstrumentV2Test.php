<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveyWorkflowTransition;
use App\Models\User;
use App\Modules\Surveys\Actions\InstallPharmVrInstrumentsV2Action;
use App\Modules\Surveys\Services\PharmVrInstrumentV2Catalog;
use App\Modules\Surveys\Services\SurveyInstrumentWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmVrInstrumentV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_scientific_catalog_is_exact_and_has_required_structure(): void
    {
        $catalog = app(PharmVrInstrumentV2Catalog::class)->instruments('researcher@example.test');

        $this->assertSame('d3f4c28b0b79bb4c1b501fba09e22ff06bcd81fe35d1375e84e024f8812851dd', hash('sha256', json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        $this->assertSame(['S01-STUDENT-NEEDS-v2.0', 'S02-LECTURER-NEEDS-v2.0', 'S03-PRACTITIONER-INTERVIEW-v2.0'], array_column($catalog, 'identifier'));
        $this->assertSame([34, 35, 26], collect($catalog)->map(fn (array $instrument): int => collect($instrument['pages'])->sum(fn (array $page): int => count($page['questions'])))->all());
        $this->assertCount(95, collect($catalog)->flatMap(fn (array $instrument): array => collect($instrument['pages'])->flatMap(fn (array $page): array => $page['questions'])->all())->pluck('key')->unique());

        $student = collect($catalog)->firstWhere('code', PharmVrInstrumentV2Catalog::STUDENT);
        $studentQuestions = collect($student['pages'])->flatMap(fn (array $page): array => $page['questions'])->keyBy('key');
        $this->assertSame(SurveyQuestion::TYPE_LIKERT, $studentQuestions['S01-C01']['type']);
        $this->assertSame('Belum mempelajari', $studentQuestions['S01-C01']['settings']['scale_labels']['NA']);
        $this->assertSame(3, $studentQuestions['S01-F01']['settings']['max_selections']);
        $this->assertStringContainsString('Jangan dijumlahkan menjadi satu skor total', $student['analysis']);

        $lecturer = collect($catalog)->firstWhere('code', PharmVrInstrumentV2Catalog::LECTURER);
        $this->assertStringContainsString('Jangan menyebut TPACK scale', $lecturer['analysis']);
        $practitioner = collect($catalog)->firstWhere('code', PharmVrInstrumentV2Catalog::PRACTITIONER);
        $this->assertSame('20–30 menit', $practitioner['duration']);
    }

    public function test_dry_run_and_install_are_safe_idempotent_and_leave_existing_response_unchanged(): void
    {
        [$owner, $project] = $this->projectFixture();
        $legacy = Survey::create([
            'project_id' => $project->id, 'created_by' => $owner->id, 'title' => 'Survei lama', 'slug' => 'survei-lama',
            'status' => Survey::STATUS_CLOSED, 'identity_mode' => Survey::IDENTITY_HIDDEN, 'instrument_type' => Survey::INSTRUMENT_OTHER, 'is_public' => false,
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $legacy->id, 'response_token_hash' => hash('sha256', 'existing-response'),
            'status' => SurveyResponse::STATUS_SUBMITTED, 'submitted_at' => now(), 'is_test_response' => false, 'excluded_from_analysis' => false,
        ]);

        $this->artisan('researchhub:install-pharmvr-instruments-v2', ['--project' => $project->id, '--contact' => 'researcher@example.test', '--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, Survey::count());

        $installer = app(InstallPharmVrInstrumentsV2Action::class);
        $first = $installer->handle($owner, $project, 'researcher@example.test');
        $second = $installer->handle($owner, $project, 'researcher@example.test');

        $this->assertSame(3, collect($first)->where('created', true)->count());
        $this->assertSame(0, collect($second)->where('created', true)->count());
        $this->assertSame(4, Survey::count());
        $this->assertSame(95, SurveyQuestion::whereIn('survey_id', collect($first)->pluck('survey.id'))->count());
        $this->assertSame(3, SurveyWorkflowTransition::where('to_state', SurveyInstrumentWorkflowService::DRAFT)->count());
        $this->assertDatabaseHas('survey_responses', ['id' => $response->id, 'survey_id' => $legacy->id, 'status' => SurveyResponse::STATUS_SUBMITTED]);
        $this->assertSame(0, SurveyResponse::whereIn('survey_id', collect($first)->pluck('survey.id'))->count());

        foreach (collect($first)->pluck('survey') as $survey) {
            $this->assertSame(Survey::STATUS_DRAFT, $survey->status);
            $this->assertFalse($survey->is_public);
            $this->assertSame('2.0', $survey->instrument_version);
            $this->get(route('survey.show', ['survey' => $survey]))->assertForbidden();
        }
    }

    public function test_only_project_owner_or_authorized_admin_can_open_draft_workspace(): void
    {
        [$owner, $project] = $this->projectFixture();
        $survey = app(InstallPharmVrInstrumentsV2Action::class)->handle($owner, $project, 'researcher@example.test')[0]['survey'];
        $other = User::factory()->create();
        $other->assignRole('admin');

        $this->actingAs($other)->get(route('admin.surveys.builder.index', ['survey' => $survey]))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.surveys.builder.index', ['survey' => $survey]))->assertOk()->assertSeeText('Tindakan berikutnya')->assertSeeText('S01-STUDENT-NEEDS-v2.0');
    }

    /** @return array{User, ResearchProject} */
    private function projectFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create(['email' => 'researcher@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create(['owner_id' => $owner->id, 'title' => 'Riset PharmVR', 'slug' => 'riset-pharmvr', 'status' => ResearchProject::STATUS_ACTIVE]);

        return [$owner, $project];
    }
}
