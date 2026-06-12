<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyQuestionEditSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_risky_question_edits_are_blocked_after_responses_exist(): void
    {
        [$owner, $survey, $page, $question, $response] = $this->answeredSurveyFixture();

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->put(route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]), [
                'question_key' => 'changed_key',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
                'label' => 'Updated label',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('question_key');

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->put(route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]), [
                'question_key' => $question->question_key,
                'type' => SurveyQuestion::TYPE_NUMBER,
                'label' => 'Updated label',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('type');

        $question->refresh();
        $this->assertSame('feedback', $question->question_key);
        $this->assertSame(SurveyQuestion::TYPE_SHORT_TEXT, $question->type);
    }

    public function test_safe_question_edits_are_allowed_after_responses_exist(): void
    {
        [$owner, $survey, $page, $question, $response] = $this->answeredSurveyFixture();

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]), [
                'question_key' => $question->question_key,
                'type' => $question->type,
                'label' => 'Updated wording',
                'help_text' => 'Updated help',
                'is_required' => '1',
                'sort_order' => 9,
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $question->refresh();
        $this->assertSame('Updated wording', $question->label);
        $this->assertSame('Updated help', $question->help_text);
        $this->assertTrue($question->is_required);
        $this->assertSame(9, $question->sort_order);
    }

    public function test_options_and_settings_edits_are_blocked_after_responses_exist(): void
    {
        [$owner, $survey, $page, $question, $response] = $this->answeredSurveyFixture([
            'type' => SurveyQuestion::TYPE_LIKERT,
            'options' => ['choices' => ['1', '2', '3', '4', '5']],
            'settings' => ['scale' => ['1', '2', '3', '4', '5']],
        ]);

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->put(route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]), [
                'question_key' => $question->question_key,
                'type' => $question->type,
                'label' => 'Updated label',
                'options_json' => '{"choices":["1","2","3"]}',
                'settings_json' => '{"scale":["1","2","3","4","5"]}',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('options');

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->put(route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]), [
                'question_key' => $question->question_key,
                'type' => $question->type,
                'label' => 'Updated label',
                'options_json' => '{"choices":["1","2","3","4","5"]}',
                'settings_json' => '{"scale":["1","2","3"]}',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('settings');
    }

    public function test_question_and_page_deletes_are_blocked_after_responses_exist(): void
    {
        [$owner, $survey, $page, $question, $response] = $this->answeredSurveyFixture();

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->delete(route('admin.surveys.builder.questions.delete', ['survey' => $survey, 'question' => $question]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('question');

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->delete(route('admin.surveys.builder.pages.delete', ['survey' => $survey, 'page' => $page]))
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('page');

        $this->assertDatabaseHas('survey_questions', ['id' => $question->id]);
        $this->assertDatabaseHas('survey_pages', ['id' => $page->id]);
    }

    /**
     * @return array{0: User, 1: Survey, 2: SurveyPage, 3: SurveyQuestion, 4: SurveyResponse}
     */
    private function answeredSurveyFixture(array $questionOverrides = []): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Answered Survey Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Answered Survey',
        ]);
        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'title' => 'Page A',
        ]);
        $question = SurveyQuestion::create(array_merge([
            'survey_id' => $survey->id,
            'page_id' => $page->id,
            'question_key' => 'feedback',
            'type' => SurveyQuestion::TYPE_SHORT_TEXT,
            'label' => 'Feedback',
        ], $questionOverrides));
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'status' => SurveyResponse::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_question_id' => $question->id,
            'question_key' => $question->question_key,
            'answer_value' => 'Stored answer',
        ]);

        return [$owner, $survey, $page, $question, $response];
    }
}
