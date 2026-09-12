<?php

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\Documents\Actions\CreateDocumentAction;
use App\Modules\Projects\Services\ProjectResearchJourneyService;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Database\Seeders\DocumentCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhUx001ClosureAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_project_dashboard_keeps_one_primary_action_and_all_secondary_content(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('rhux001a-one@example.test');
        $project = $this->project($admin, 'Proyek Tunggal Sintetis dengan Judul Panjang untuk Pemeriksaan Konteks');

        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, preg_match_all('/\sdata-primary-action(?:\s|>)/', $html));
        $this->assertLessThanOrEqual(3, preg_match_all('/\sdata-initial-follow-up(?:\s|>)/', $html));
        $response
            ->assertSeeText($project->title)
            ->assertSeeText('Lihat semua rincian')
            ->assertSee('data-dashboard-details', false);

        $detailsStart = strpos($html, '<details class="rh-card rh-details" data-dashboard-details>');
        $this->assertNotFalse($detailsStart);
        $detailsHtml = substr($html, $detailsStart);

        foreach ([
            'data-dashboard-card="stat"',
            'data-dashboard-card="research-journey"',
            'data-dashboard-card="timeline-focus"',
            'data-dashboard-card="recent-documents"',
            'data-dashboard-card="recent-surveys"',
            'data-dashboard-card="quick-actions"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $detailsHtml);
        }
    }

    public function test_journey_render_matches_service_step_status_metrics_and_progress_formula(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('rhux001a-journey@example.test');
        $project = $this->project($admin, 'Fixture Kesetaraan Alur Riset');

        $journey = app(ProjectResearchJourneyService::class)->build($project->fresh());
        $response = $this->actingAs($admin)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertOk();
        $html = $response->getContent();

        $this->assertCount(11, $journey['steps']);
        $this->assertSame(11, preg_match_all('/\sdata-journey-step\s/', $html));

        $formulaScore = $journey['steps']->sum(fn (array $step): float => match ($step['status']) {
            ProjectResearchJourneyService::STATUS_COMPLETED => 1.0,
            ProjectResearchJourneyService::STATUS_IN_PROGRESS => 0.5,
            default => 0.0,
        });
        $formulaProgress = (int) round(($formulaScore / $journey['steps']->count()) * 100);

        $this->assertSame($formulaProgress, $journey['progress_percentage']);
        $response->assertSeeText($formulaProgress.'%');

        foreach ($journey['steps'] as $step) {
            $pattern = '/<details[^>]*data-step-key="'.preg_quote($step['key'], '/').'"[^>]*>(.*?)<\/details>/s';
            $this->assertSame(1, preg_match($pattern, $html, $matches), "Step {$step['key']} harus dirender.");
            $stepHtml = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $this->assertStringContainsString($step['status_label'], $stepHtml, "Status {$step['key']} harus sama dengan service.");
            $this->assertStringContainsString($step['action_url'], $matches[1], "URL tindakan {$step['key']} harus tetap berasal dari service.");

            preg_match_all('/<dd[^>]*>(.*?)<\/dd>/s', $matches[1], $metricMatches);
            $renderedMetricValues = collect($metricMatches[1] ?? [])
                ->map(fn (string $value): string => trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')))
                ->values()
                ->all();

            $this->assertSame(
                array_values(array_map('strval', $step['metrics'])),
                $renderedMetricValues,
                "Nilai metrik {$step['key']} harus sama dan berurutan seperti service.",
            );
        }
    }

    public function test_researcher_role_does_not_gain_admin_navigation_access(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $researcher = User::factory()->create([
            'name' => 'Peneliti Terbatas Sintetis dengan Nama dan Role Panjang',
            'email' => 'rhux001a-limited@example.test',
        ]);
        $researcher->assignRole('researcher');
        $this->project($researcher, 'Proyek yang Dimiliki Peneliti Terbatas');

        $this->actingAs($researcher)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_guest_and_outsider_direct_access_are_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DocumentCategorySeeder::class);

        $owner = $this->adminUser('rhux001a-owner@example.test');
        $outsider = $this->adminUser('rhux001a-outsider@example.test');
        $project = $this->project($owner, 'Proyek Privat Sintetis');
        $category = DocumentCategory::query()->where('slug', 'proposal')->firstOrFail();
        $document = app(CreateDocumentAction::class)->handle($owner, $project, $category, [
            'title' => 'Dokumen Privat Sintetis',
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Survei Privat Sintetis',
        ]);

        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs($outsider)
            ->get(route('admin.projects.journey.show', ['researchProject' => $project]))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('admin.documents.review-links.index', ['document' => $document]))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertForbidden();
    }

    public function test_logout_invalidates_session_and_returns_to_login(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->adminUser('rhux001a-logout@example.test');

        $this->withSession(['rhux001a_session_marker' => 'present']);
        $oldToken = session()->token();

        $response = $this->actingAs($admin)
            ->post(route('filament.admin.auth.logout'));

        $response
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionMissing('rhux001a_session_marker');
        $this->assertGuest();
        $this->assertNotSame($oldToken, session()->token());
    }

    private function adminUser(string $email): User
    {
        $user = User::factory()->create([
            'name' => 'Peneliti dengan Nama Sintetis yang Sangat Panjang untuk Pemeriksaan Tampilan',
            'email' => $email,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function project(User $owner, string $title): ResearchProject
    {
        return ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => ResearchProject::STATUS_ACTIVE,
            'target_finished_at' => today()->addMonths(3),
        ]);
    }
}
