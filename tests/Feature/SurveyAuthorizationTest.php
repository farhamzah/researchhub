<?php

namespace Tests\Feature;

use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SurveyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_policy_allows_owner_and_authorized_member_but_blocks_viewer_management(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        $coResearcher = User::factory()->create();
        $viewer = User::factory()->create();

        $this->activeMember($project, $coResearcher, ProjectMember::ROLE_CO_RESEARCHER);
        $this->activeMember($project, $viewer, ProjectMember::ROLE_VIEWER);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $survey));
        $this->assertTrue(Gate::forUser($coResearcher)->allows('publish', $survey));
        $this->assertTrue(Gate::forUser($viewer)->allows('view', $survey));
        $this->assertFalse(Gate::forUser($viewer)->allows('publish', $survey));
    }

    public function test_unauthorized_user_cannot_publish_survey(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        $outsider = User::factory()->create();

        $this->assertFalse(Gate::forUser($outsider)->allows('view', $survey));

        $this->expectException(AuthorizationException::class);

        app(PublishSurveyAction::class)->handle($outsider, $survey);
    }

    public function test_public_survey_routes_do_not_require_authenticated_user(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSee($survey->title);
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey}
     */
    private function surveyFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Survey Authorization Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Pretest',
        ]);

        return [$owner, $project, $survey];
    }

    private function activeMember(ResearchProject $project, User $user, string $role): ProjectMember
    {
        return ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);
    }
}
