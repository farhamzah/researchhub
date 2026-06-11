<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Actions\CloseSurveyAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSurveySubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_public_survey_accepts_valid_response_without_exposing_project_data(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture(Survey::IDENTITY_PSEUDONYM);
        $this->attachQuestions($survey);
        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSee($survey->title)
            ->assertDontSee($project->title);

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'answers' => [
                'name_need' => 'Need better dashboards',
                'role' => 'student',
                'features' => ['analytics', 'export'],
                'satisfaction' => '5',
                'usability_matrix' => [
                    'Navigation' => '4',
                    'Clarity' => '5',
                ],
                'age' => '21',
                'survey_date' => '2026-06-12',
                'consent' => '1',
            ],
            'identity' => [
                'name' => 'Should Not Be Stored In Pseudonym Mode',
            ],
        ])
            ->assertOk()
            ->assertSee('Response submitted')
            ->assertDontSee('Should Not Be Stored In Pseudonym Mode');

        $response = SurveyResponse::with(['respondent', 'answers'])->firstOrFail();

        $this->assertSame(SurveyResponse::STATUS_SUBMITTED, $response->status);
        $this->assertSame('R001', $response->respondent->pseudonym_code);
        $this->assertNull($response->respondent->name);
        $this->assertCount(8, $response->answers);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'survey.response_submitted',
            'subject_id' => $response->id,
        ]);
        $metadata = json_encode(ActivityLog::where('action', 'survey.response_submitted')->firstOrFail()->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Need better dashboards', $metadata);
    }

    public function test_draft_and_closed_surveys_reject_public_responses(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        $this->attachQuestions($survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSee('This survey is unavailable')
            ->assertDontSee($survey->title)
            ->assertDontSee($project->title);

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'answers' => ['name_need' => 'Blocked'],
        ])
            ->assertForbidden()
            ->assertSee('This survey is unavailable');

        app(PublishSurveyAction::class)->handle($owner, $survey);
        app(CloseSurveyAction::class)->handle($owner, $survey->fresh());

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'answers' => ['name_need' => 'Blocked again'],
        ])
            ->assertForbidden()
            ->assertSee('This survey is unavailable');

        $this->assertSame(0, SurveyResponse::count());
        $this->assertSame(2, ActivityLog::where('action', 'survey.response_rejected')->count());
    }

    public function test_public_submission_validates_required_answers_and_allowed_options(): void
    {
        [$owner, $project, $survey] = $this->surveyFixture();
        $this->attachQuestions($survey);
        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->from(route('survey.show', ['survey' => $survey->slug]))
            ->post(route('survey.responses.store', ['survey' => $survey->slug]), [
                'answers' => [
                    'role' => 'invalid-role',
                    'consent' => '0',
                ],
            ])
            ->assertRedirect(route('survey.show', ['survey' => $survey->slug]))
            ->assertSessionHasErrors('answers.name_need');

        $this->from(route('survey.show', ['survey' => $survey->slug]))
            ->post(route('survey.responses.store', ['survey' => $survey->slug]), [
                'answers' => [
                    'name_need' => 'Consent should be required',
                    'role' => 'student',
                    'consent' => '0',
                ],
            ])
            ->assertRedirect(route('survey.show', ['survey' => $survey->slug]))
            ->assertSessionHasErrors('answers.consent');

        $this->from(route('survey.show', ['survey' => $survey->slug]))
            ->post(route('survey.responses.store', ['survey' => $survey->slug]), [
                'answers' => [
                    'name_need' => 'Need a valid role option',
                    'role' => 'invalid-role',
                    'consent' => '1',
                ],
            ])
            ->assertRedirect(route('survey.show', ['survey' => $survey->slug]))
            ->assertSessionHasErrors('answers.role');

        $this->assertSame(0, SurveyResponse::count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'survey.response_rejected',
        ]);
    }

    /**
     * @return array{0: User, 1: ResearchProject, 2: Survey}
     */
    private function surveyFixture(string $identityMode = Survey::IDENTITY_HIDDEN): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Private Survey Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Usability survey',
            'identity_mode' => $identityMode,
        ]);

        return [$owner, $project, $survey];
    }

    private function attachQuestions(Survey $survey): void
    {
        $page = SurveyPage::create([
            'survey_id' => $survey->id,
            'title' => 'Main page',
        ]);

        $questions = [
            [
                'question_key' => 'name_need',
                'type' => SurveyQuestion::TYPE_SHORT_TEXT,
                'label' => 'What do you need?',
                'is_required' => true,
            ],
            [
                'question_key' => 'role',
                'type' => SurveyQuestion::TYPE_SINGLE_CHOICE,
                'label' => 'Role',
                'options' => ['choices' => ['student', 'lecturer']],
                'is_required' => true,
            ],
            [
                'question_key' => 'features',
                'type' => SurveyQuestion::TYPE_MULTIPLE_CHOICE,
                'label' => 'Features',
                'options' => ['choices' => ['analytics', 'export', 'guidance']],
            ],
            [
                'question_key' => 'satisfaction',
                'type' => SurveyQuestion::TYPE_LIKERT,
                'label' => 'Satisfaction',
                'options' => ['scale' => [1, 2, 3, 4, 5]],
            ],
            [
                'question_key' => 'usability_matrix',
                'type' => SurveyQuestion::TYPE_LIKERT_MATRIX,
                'label' => 'Usability matrix',
                'options' => [
                    'rows' => ['Navigation', 'Clarity'],
                    'columns' => [1, 2, 3, 4, 5],
                ],
            ],
            [
                'question_key' => 'age',
                'type' => SurveyQuestion::TYPE_NUMBER,
                'label' => 'Age',
            ],
            [
                'question_key' => 'survey_date',
                'type' => SurveyQuestion::TYPE_DATE,
                'label' => 'Date',
            ],
            [
                'question_key' => 'consent',
                'type' => SurveyQuestion::TYPE_CONSENT,
                'label' => 'Consent',
                'is_required' => true,
            ],
        ];

        foreach ($questions as $index => $question) {
            SurveyQuestion::create($question + [
                'survey_id' => $survey->id,
                'page_id' => $page->id,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
