<?php

namespace Tests\Feature;

use App\Models\ProjectMember;
use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyQuestionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_builder_and_create_page_and_question(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Survey Builder')
            ->assertSee('Question List')
            ->assertSee('Identity Mode')
            ->assertSee('Do not add sensitive personal data questions unless required by protocol and ethics approval')
            ->assertSee('Add Question')
            ->assertSee('Open Public Survey')
            ->assertSee('Open Responses')
            ->assertSee('Open Analysis')
            ->assertSee('Open Scoring');

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.pages.store', ['survey' => $survey]), [
                'title' => 'Section A',
                'description' => 'Opening questions',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $page = $survey->pages()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'page_id' => $page->id,
                'label' => 'Primary need',
                'type' => SurveyQuestion::TYPE_SINGLE_CHOICE,
                'options_json' => '{"choices":["media","assessment"]}',
                'is_required' => '1',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $question = $survey->questions()->firstOrFail();

        $this->assertSame('primary_need', $question->question_key);
        $this->assertSame(SurveyQuestion::TYPE_SINGLE_CHOICE, $question->type);
        $this->assertSame(['choices' => ['media', 'assessment']], $question->options);
        $this->assertTrue($question->is_required);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.page_created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.question_created']);
    }

    public function test_authorized_user_can_create_structured_choice_likert_and_text_questions(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'label' => 'Preferred learning format',
                'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
                'choice_options' => ['Video', 'Worksheet', '', 'Discussion'],
                'is_required' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'label' => 'The application is easy to use',
                'type' => SurveyQuestion::TYPE_LIKERT,
                'likert_scale' => ['1', '2', '3', '4', '5'],
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'label' => 'Additional feedback',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $choice = $survey->questions()->where('question_key', 'preferred_learning_format')->firstOrFail();
        $likert = $survey->questions()->where('question_key', 'the_application_is_easy_to_use')->firstOrFail();

        $this->assertSame(['choices' => ['Video', 'Worksheet', 'Discussion']], $choice->options);
        $this->assertNull($choice->settings);
        $this->assertSame(['scale' => ['1', '2', '3', '4', '5']], $likert->settings);
        $this->assertSame([1, 2, 3], $survey->questions()->pluck('sort_order')->all());

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Preferred learning format')
            ->assertSee('Multiple Choice')
            ->assertSee('Required')
            ->assertSee('The application is easy to use')
            ->assertSee('Likert')
            ->assertSee('Additional feedback')
            ->assertSee('Short Text');
    }

    public function test_multiple_choice_max_selections_can_be_saved_from_builder(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'question_key' => 'priority_features',
                'label' => 'Pilih maksimal 3 fitur prioritas.',
                'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
                'choice_options' => ['A', 'B', 'C', 'D'],
                'max_selections' => 3,
                'is_required' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $question = $survey->questions()->where('question_key', 'priority_features')->firstOrFail();
        $this->assertSame(3, $question->settings['max_selections']);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Max selections')
            ->assertSee('value="3"', false);

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]), [
                'question_key' => 'priority_features',
                'label' => 'Pilih maksimal 2 fitur prioritas.',
                'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
                'choice_options' => ['A', 'B', 'C', 'D'],
                'max_selections' => 2,
                'is_required' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->assertSame(2, $question->fresh()->settings['max_selections']);
    }

    public function test_builder_does_not_expose_respondent_identity(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        $respondent = Respondent::create([
            'project_id' => $project->id,
            'survey_id' => $survey->id,
            'pseudonym_code' => 'R-001',
            'name' => 'Sensitive Respondent',
            'email' => 'sensitive@example.test',
            'identifier' => 'PRIVATE-ID',
        ]);
        SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_id' => $respondent->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Survey ini sudah memiliki respons')
            ->assertDontSee('Sensitive Respondent')
            ->assertDontSee('sensitive@example.test')
            ->assertDontSee('PRIVATE-ID');
    }

    public function test_question_key_and_options_validation_are_enforced(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'question_key' => 'unsafe key!',
                'label' => 'Unsafe key',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('question_key');

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'question_key' => 'choice',
                'label' => 'Choice',
                'type' => SurveyQuestion::TYPE_SINGLE_CHOICE,
                'options_json' => '{"choices":[]}',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('options');

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'question_key' => 'unique_key',
                'label' => 'Unique Key',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'question_key' => 'unique_key',
                'label' => 'Duplicate Key',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('question_key');
    }

    public function test_authorization_blocks_unauthorized_member_from_managing_questions(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        $viewer = User::factory()->create();
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectMember::ROLE_VIEWER,
            'status' => ProjectMember::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.surveys.builder.questions.store', ['survey' => $survey]), [
                'label' => 'Blocked',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
            ])
            ->assertForbidden();
    }

    public function test_authorized_user_can_update_duplicate_and_delete_when_no_responses_exist(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        $page = $survey->pages()->create([
            'title' => 'Page before edit',
            'sort_order' => 1,
        ]);
        $question = $survey->questions()->create([
            'page_id' => $page->id,
            'question_key' => 'feedback',
            'type' => SurveyQuestion::TYPE_LONG_TEXT,
            'label' => 'Feedback',
        ]);

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.pages.update', ['survey' => $survey, 'page' => $page]), [
                'title' => 'Page after edit',
                'description' => 'Updated page description',
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $page->refresh();
        $this->assertSame('Page after edit', $page->title);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.page_updated']);

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]), [
                'question_key' => 'feedback_updated',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
                'label' => 'Updated feedback',
                'help_text' => 'Short answer',
                'is_required' => '1',
                'sort_order' => 5,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $question->refresh();
        $this->assertSame('feedback_updated', $question->question_key);
        $this->assertSame(SurveyQuestion::TYPE_SHORT_TEXT, $question->type);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.question_updated']);

        $this->actingAs($owner)
            ->post(route('admin.surveys.builder.questions.duplicate', ['survey' => $survey, 'question' => $question]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_questions', ['question_key' => 'feedback_updated_copy']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.question_duplicated']);

        $copy = $survey->questions()->where('question_key', 'feedback_updated_copy')->firstOrFail();
        $this->actingAs($owner)
            ->delete(route('admin.surveys.builder.questions.delete', ['survey' => $survey, 'question' => $copy]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->assertDatabaseMissing('survey_questions', ['id' => $copy->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.question_deleted']);

        $this->actingAs($owner)
            ->delete(route('admin.surveys.builder.pages.delete', ['survey' => $survey, 'page' => $page]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $this->assertDatabaseMissing('survey_pages', ['id' => $page->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'survey.page_deleted']);
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey}
     */
    private function surveyFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Question Management Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Question Management Survey',
        ]);

        return [$owner, $project, $survey];
    }
}
