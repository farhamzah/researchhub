<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\Projects\Actions\CreateProjectTimelineTaskAction;
use App\Modules\Projects\Services\ProjectTimelineProgressService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_timeline_models_use_uuid_primary_keys(): void
    {
        [$owner, $project] = $this->projectFixture();

        $milestone = ProjectMilestone::create([
            'research_project_id' => $project->id,
            'title' => 'Literature Review',
            'created_by' => $owner->id,
        ]);
        $task = ProjectTimelineTask::create([
            'research_project_id' => $project->id,
            'project_milestone_id' => $milestone->id,
            'title' => 'Read papers',
            'created_by' => $owner->id,
        ]);

        $this->assertIsString($milestone->id);
        $this->assertIsString($task->id);
        $this->assertFalse($milestone->incrementing);
        $this->assertFalse($task->incrementing);
    }

    public function test_owner_can_manage_timeline_and_activity_logs_are_safe(): void
    {
        Carbon::setTestNow('2026-06-12');
        [$owner, $project] = $this->projectFixture();

        $this->actingAs($owner)
            ->get(route('admin.projects.timeline.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('Project Timeline')
            ->assertSee('Progress Summary');

        $this->actingAs($owner)
            ->post(route('admin.projects.timeline.milestones.store', ['researchProject' => $project]), [
                'title' => 'Instrument Development',
                'description' => 'Private milestone notes should not be logged.',
                'status' => ProjectMilestone::STATUS_IN_PROGRESS,
                'planned_start_date' => '2026-06-01',
                'planned_end_date' => '2026-06-10',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $milestone = ProjectMilestone::firstOrFail();

        $this->actingAs($owner)
            ->post(route('admin.projects.timeline.tasks.store', ['researchProject' => $project]), [
                'project_milestone_id' => $milestone->id,
                'title' => 'Build draft questionnaire',
                'description' => 'Private task notes should not be logged.',
                'status' => ProjectMilestone::STATUS_IN_PROGRESS,
                'progress_percentage' => 40,
                'weight' => 2,
                'planned_start_date' => '2026-06-01',
                'planned_end_date' => '2026-06-10',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $task = ProjectTimelineTask::firstOrFail();

        $this->actingAs($owner)
            ->put(route('admin.projects.timeline.tasks.update', ['researchProject' => $project, 'task' => $task]), [
                'project_milestone_id' => $milestone->id,
                'title' => 'Build draft questionnaire',
                'status' => ProjectMilestone::STATUS_COMPLETED,
                'progress_percentage' => 55,
                'weight' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('admin.projects.timeline.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Delayed');

        $this->actingAs($owner)
            ->delete(route('admin.projects.timeline.tasks.delete', ['researchProject' => $project, 'task' => $task]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_timeline_tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project_milestone.created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project_timeline_task.created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project_timeline_task.updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project_timeline_task.deleted']);

        $logPayload = ActivityLog::query()
            ->whereIn('action', [
                'project_milestone.created',
                'project_timeline_task.created',
                'project_timeline_task.updated',
                'project_timeline_task.deleted',
            ])
            ->get()
            ->map(fn (ActivityLog $log): string => json_encode($log->metadata, JSON_THROW_ON_ERROR))
            ->implode("\n");

        $this->assertStringContainsString('progress_percentage', $logPayload);
        $this->assertStringNotContainsString('Private milestone notes', $logPayload);
        $this->assertStringNotContainsString('Private task notes', $logPayload);
    }

    public function test_timeline_authorization_blocks_public_and_unauthorized_users(): void
    {
        [$owner, $project] = $this->projectFixture();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $this->get(route('admin.projects.timeline.index', ['researchProject' => $project]))
            ->assertRedirect();

        $this->actingAs($outsider)
            ->get(route('admin.projects.timeline.index', ['researchProject' => $project]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.projects.timeline.index', ['researchProject' => $project]))
            ->assertOk()
            ->assertSee('View only', false);

        $this->actingAs($viewer)
            ->post(route('admin.projects.timeline.milestones.store', ['researchProject' => $project]), [
                'title' => 'Unauthorized milestone',
                'status' => ProjectMilestone::STATUS_NOT_STARTED,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('admin.projects.timeline.index', ['researchProject' => $project]))
            ->assertOk();
    }

    public function test_progress_summary_milestone_progress_clamp_and_project_mismatch_rules(): void
    {
        Carbon::setTestNow('2026-06-12');
        [$owner, $project] = $this->projectFixture();
        [$otherOwner, $otherProject] = $this->projectFixture('Other Project');

        $milestone = ProjectMilestone::create([
            'research_project_id' => $project->id,
            'title' => 'Data Collection',
            'status' => ProjectMilestone::STATUS_IN_PROGRESS,
            'planned_end_date' => '2026-06-01',
            'created_by' => $owner->id,
        ]);
        $otherMilestone = ProjectMilestone::create([
            'research_project_id' => $otherProject->id,
            'title' => 'Other Milestone',
            'created_by' => $otherOwner->id,
        ]);

        ProjectTimelineTask::create([
            'research_project_id' => $project->id,
            'project_milestone_id' => $milestone->id,
            'title' => 'Completed task',
            'status' => ProjectMilestone::STATUS_COMPLETED,
            'progress_percentage' => 10,
            'weight' => 2,
        ]);
        ProjectTimelineTask::create([
            'research_project_id' => $project->id,
            'project_milestone_id' => $milestone->id,
            'title' => 'Half task',
            'status' => ProjectMilestone::STATUS_IN_PROGRESS,
            'progress_percentage' => 50,
            'weight' => 1,
            'planned_end_date' => '2026-06-01',
        ]);
        ProjectTimelineTask::create([
            'research_project_id' => $project->id,
            'project_milestone_id' => $milestone->id,
            'title' => 'Cancelled task',
            'status' => ProjectMilestone::STATUS_CANCELLED,
            'progress_percentage' => 100,
            'weight' => 10,
        ]);

        $service = app(ProjectTimelineProgressService::class);

        $this->assertSame(83, $service->projectSummary($project)['progress_percentage']);
        $this->assertSame(83, $service->milestoneSummary($milestone)['progress_percentage']);
        $this->assertTrue($service->milestoneSummary($milestone)['is_delayed']);

        $createdTask = app(CreateProjectTimelineTaskAction::class)->handle($owner, $project, [
            'title' => 'Clamped task',
            'status' => ProjectMilestone::STATUS_IN_PROGRESS,
            'progress_percentage' => 150,
            'weight' => 1,
        ]);
        $this->assertSame(100, $createdTask->progress_percentage);

        $this->expectException(ValidationException::class);
        app(CreateProjectTimelineTaskAction::class)->handle($owner, $project, [
            'project_milestone_id' => $otherMilestone->id,
            'title' => 'Wrong project task',
            'status' => ProjectMilestone::STATUS_NOT_STARTED,
        ]);
    }

    /**
     * @return array{0: User, 1: ResearchProject}
     */
    private function projectFixture(string $title = 'Timeline Project'): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        return [$owner, $project];
    }
}
