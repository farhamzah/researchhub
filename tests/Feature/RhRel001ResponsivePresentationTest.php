<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhRel001ResponsivePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_panel_contains_mobile_width_and_preserves_preview_and_error_states(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $builderUrl = route('admin.surveys.builder.index', ['survey' => $survey]);
        $questionSnapshot = $survey->questions()->orderBy('sort_order')->get()->map->toArray()->all();

        $this->actingAs($owner)
            ->get($builderUrl)
            ->assertOk()
            ->assertSee('data-advanced-settings class="mt-6 min-w-0', false)
            ->assertSee('class="mt-4 grid min-w-0 gap-3 md:grid-cols-3"', false)
            ->assertSee('data-fill-missing-pages-card', false)
            ->assertSee('class="mt-1 break-words text-sm text-slate-700"', false)
            ->assertDontSee('data-fill-missing-pages-card class="overflow-hidden', false);

        $previewResponse = $this->actingAs($owner)
            ->post(route('admin.surveys.builder.bulk-questions.preview', ['survey' => $survey]), [
                'bulk_input' => "PAGE: Bagian Sintetis\nTYPE: short_text\n\nREL-01 | Pertanyaan sintetis untuk pratinjau.",
                'indicator_strategy' => 'skip',
            ])
            ->assertRedirect($builderUrl)
            ->assertSessionHas('bulk_question_preview');

        $previewPage = $this->followRedirects($previewResponse)
            ->assertOk()
            ->assertSee('data-bulk-preview-result', false)
            ->assertSeeText('Pertanyaan sintetis untuk pratinjau.');

        $this->assertMatchesRegularExpression(
            '/<details[^>]*data-advanced-settings[^>]*\sopen(?:\s|>)/',
            $previewPage->getContent(),
        );

        $errorResponse = $this->actingAs($owner)
            ->post(route('admin.surveys.builder.bulk-questions.preview', ['survey' => $survey]), [
                'bulk_input' => '',
                'indicator_strategy' => 'create',
            ])
            ->assertRedirect($builderUrl)
            ->assertSessionHasErrors('bulk_input');

        $errorPage = $this->followRedirects($errorResponse)->assertOk();
        $this->assertMatchesRegularExpression(
            '/<details[^>]*data-advanced-settings[^>]*\sopen(?:\s|>)/',
            $errorPage->getContent(),
        );

        $this->assertSame($questionSnapshot, $survey->fresh()->questions()->orderBy('sort_order')->get()->map->toArray()->all());
        $this->assertSame(0, $survey->responses()->count());
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function surveyFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Proyek Sintetis RH-REL-001',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Instrumen Sintetis RH-REL-001',
            'description' => 'Fixture presentation-only.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
        ]);

        return [$owner, $survey];
    }
}
