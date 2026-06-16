<?php

namespace Tests\Feature;

use App\Models\ExpertValidator;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityQuestionFeedback;
use App\Models\SurveyReadabilityResponse;
use App\Models\SurveyReadabilityRevision;
use App\Models\SurveyReadabilityRound;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRound;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyReadabilityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_readability_round_and_add_participant(): void
    {
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->get(route('admin.surveys.readability.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Readability Test')
            ->assertSeeText('Create Readability Round');

        $this->actingAs($owner)
            ->post(route('admin.surveys.readability.rounds.store', ['survey' => $survey]), [
                'title' => 'Pilot Readability Round',
                'status' => SurveyReadabilityRound::STATUS_OPEN,
                'target_participants' => 10,
                'instructions' => 'Review clarity for PharmVR ADDIE Analysis.',
            ])
            ->assertRedirect(route('admin.surveys.readability.index', ['survey' => $survey]));

        $round = SurveyReadabilityRound::query()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('admin.surveys.readability.participants.store', ['survey' => $survey, 'round' => $round]), [
                'participant_name' => 'Pilot Student',
                'participant_email' => 'pilot@example.test',
                'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
                'institution' => 'PharmVR Campus',
            ])
            ->assertRedirect(route('admin.surveys.readability.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_readability_participants', [
            'survey_readability_round_id' => $round->id,
            'survey_id' => $survey->id,
            'participant_name' => 'Pilot Student',
            'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
        ]);
    }

    public function test_public_token_opens_and_participant_can_submit_readability_feedback_without_main_response(): void
    {
        [$owner, $survey, $round, $participant, $question] = $this->readabilityFixture();

        $response = $this->actingAs($owner)
            ->post(route('admin.surveys.readability.participants.generate-link', ['survey' => $survey, 'participant' => $participant]))
            ->assertRedirect(route('admin.surveys.readability.index', ['survey' => $survey]));

        $url = (string) $response->getSession()->get('generated_readability_url');
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->assertNotEmpty($token);
        $this->assertNotSame($token, $participant->fresh()->token_hash);

        $this->get(route('readability.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Uji Keterbacaan Instrumen')
            ->assertSeeText('Submit Readability Feedback')
            ->assertSeeText('Pertanyaan kebutuhan PharmVR');

        $this->post(route('readability.survey.store', ['token' => $token]), [
            'participant_name' => 'Pilot Student',
            'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
            'institution' => 'PharmVR Campus',
            'instruction_clarity_score' => 4,
            'overall_clarity_score' => 4,
            'terminology_clarity_score' => 3,
            'answer_option_clarity_score' => 4,
            'overall_length_score' => 5,
            'estimated_completion_minutes' => 12,
            'feedback' => [
                [
                    'survey_question_id' => $question->id,
                    'issue_type' => SurveyReadabilityQuestionFeedback::ISSUE_UNCLEAR_WORDING,
                    'comment' => 'The term immersive scenario needs simpler wording.',
                ],
            ],
            'confusing_items' => 'Question 1 has one difficult term.',
            'general_comments' => 'Overall readable for students.',
            'revision_suggestions' => 'Add an example after the PharmVR term.',
            'final_decision' => SurveyReadabilityResponse::DECISION_MINOR_REVISION,
        ])
            ->assertRedirect(route('readability.survey.show', ['token' => $token]));

        $this->get(route('readability.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Readability feedback submitted');

        $this->assertSame(1, SurveyReadabilityResponse::count());
        $this->assertSame(1, SurveyReadabilityQuestionFeedback::count());
        $this->assertGreaterThanOrEqual(1, SurveyReadabilityRevision::count());
        $this->assertSame(0, SurveyResponse::count());

        $this->actingAs($owner)
            ->get(route('admin.surveys.readability.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Submitted')
            ->assertSeeText('Understandable with minor revision');
    }

    public function test_results_report_load_and_revision_matrix_can_be_updated(): void
    {
        [$owner, $survey, $round] = $this->submittedReadabilityFixture();
        $revision = SurveyReadabilityRevision::query()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('admin.surveys.readability.results.show', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSeeText('Readability Test Results')
            ->assertSeeText('Average Readability')
            ->assertSeeText('Readable with minor revision')
            ->assertSeeText('Most Frequently Flagged Questions')
            ->assertSeeText('The term immersive scenario needs simpler wording.');

        $this->actingAs($owner)
            ->get(route('admin.surveys.readability.report', ['survey' => $survey, 'round' => $round]))
            ->assertOk()
            ->assertSeeText('MyRiset Readability Test Report')
            ->assertSeeText('Revision Matrix');

        $this->actingAs($owner)
            ->get(route('admin.surveys.readability.report.latest', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('MyRiset Readability Test Report')
            ->assertSeeText($round->title);

        $this->actingAs($owner)
            ->put(route('admin.surveys.readability.revisions.update', ['survey' => $survey, 'revision' => $revision]), [
                'revision_action' => 'Replace the difficult term with a student-facing example.',
                'status' => SurveyReadabilityRevision::STATUS_REVISED,
                'researcher_note' => 'Updated before broad distribution.',
            ])
            ->assertRedirect(route('admin.surveys.readability.index', ['survey' => $survey]));

        $this->assertDatabaseHas('survey_readability_revisions', [
            'id' => $revision->id,
            'status' => SurveyReadabilityRevision::STATUS_REVISED,
            'revision_action' => 'Replace the difficult term with a student-facing example.',
        ]);
    }

    public function test_existing_public_survey_and_expert_validation_routes_still_work(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSee($survey->title);

        $validator = ExpertValidator::create([
            'created_by' => $owner->id,
            'name' => 'Content Validator',
            'is_active' => true,
        ]);
        $round = SurveyValidationRound::create([
            'survey_id' => $survey->id,
            'research_project_id' => $survey->project_id,
            'created_by' => $owner->id,
            'title' => 'Expert Validation Round',
            'method' => SurveyValidationRound::METHOD_EXPERT_JUDGMENT,
            'rating_scale_min' => 1,
            'rating_scale_max' => 5,
            'status' => SurveyValidationRound::STATUS_OPEN,
        ]);
        $token = 'validation-token-for-regression';
        SurveyValidationAssignment::create([
            'survey_validation_round_id' => $round->id,
            'expert_validator_id' => $validator->id,
            'role' => 'content_expert',
            'status' => SurveyValidationAssignment::STATUS_LINK_GENERATED,
            'token_hash' => SurveyValidationAssignment::hashToken($token),
            'token_created_at' => now(),
            'created_by' => $owner->id,
        ]);

        $this->get(route('validation.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Validasi Ahli Instrumen')
            ->assertSeeText('Kirim Hasil Validasi');
    }

    /**
     * @return array{0: User, 1: Survey, 2: SurveyReadabilityRound, 3: SurveyReadabilityParticipant, 4: SurveyQuestion}
     */
    private function readabilityFixture(): array
    {
        [$owner, $survey] = $this->surveyFixture();
        $round = SurveyReadabilityRound::create([
            'survey_id' => $survey->id,
            'created_by' => $owner->id,
            'title' => 'Readability Pilot',
            'status' => SurveyReadabilityRound::STATUS_OPEN,
            'target_participants' => 10,
        ]);
        $participant = SurveyReadabilityParticipant::create([
            'survey_readability_round_id' => $round->id,
            'survey_id' => $survey->id,
            'participant_name' => 'Pilot Student',
            'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
            'status' => SurveyReadabilityParticipant::STATUS_PENDING,
        ]);
        $question = $survey->questions()->firstOrFail();

        return [$owner, $survey, $round, $participant, $question];
    }

    /**
     * @return array{0: User, 1: Survey, 2: SurveyReadabilityRound}
     */
    private function submittedReadabilityFixture(): array
    {
        [$owner, $survey, $round, $participant, $question] = $this->readabilityFixture();

        $participant->forceFill([
            'token_hash' => SurveyReadabilityParticipant::hashToken('submitted-token'),
            'token_created_at' => now(),
        ])->save();

        app(\App\Modules\Surveys\Actions\SubmitSurveyReadabilityResponseAction::class)->handle($participant, [
            'participant_name' => 'Pilot Student',
            'participant_type' => SurveyReadabilityParticipant::TYPE_STUDENT,
            'institution' => 'PharmVR Campus',
            'instruction_clarity_score' => 4,
            'overall_clarity_score' => 4,
            'terminology_clarity_score' => 3,
            'answer_option_clarity_score' => 4,
            'overall_length_score' => 5,
            'estimated_completion_minutes' => 12,
            'feedback' => [
                [
                    'survey_question_id' => $question->id,
                    'issue_type' => SurveyReadabilityQuestionFeedback::ISSUE_UNCLEAR_WORDING,
                    'comment' => 'The term immersive scenario needs simpler wording.',
                ],
            ],
            'general_comments' => 'Overall readable for students.',
            'revision_suggestions' => 'Add an example after the PharmVR term.',
            'final_decision' => SurveyReadabilityResponse::DECISION_MINOR_REVISION,
        ]);

        return [$owner, $survey, $round];
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function surveyFixture(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = User::factory()->create();
        $owner->assignRole('admin');

        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Disertasi S3',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Analisa Sistem',
            'description' => 'Student needs analysis questionnaire for PharmVR Analysis ADDIE stage.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        SurveyQuestion::create([
            'survey_id' => $survey->id,
            'question_key' => 'need_1',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Pertanyaan kebutuhan PharmVR',
            'help_text' => 'Rate the need for immersive scenarios.',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        return [$owner, $survey];
    }
}
