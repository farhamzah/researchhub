<?php

namespace App\Modules\Surveys\Actions;

use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityQuestionFeedback;
use App\Models\SurveyReadabilityResponse;
use App\Models\SurveyReadabilityRevision;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitSurveyReadabilityResponseAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SurveyReadabilityParticipant $participant, array $data, ?Request $request = null): SurveyReadabilityResponse
    {
        $participant->loadMissing('round.survey.project', 'response.questionFeedback');

        if (! $participant->isAccessible()) {
            throw ValidationException::withMessages([
                'readability' => 'This readability test link is no longer available.',
            ]);
        }

        $survey = $participant->round->survey;
        $feedbackRows = $this->normalizedFeedback($data['feedback'] ?? []);

        return DB::transaction(function () use ($participant, $survey, $data, $feedbackRows, $request): SurveyReadabilityResponse {
            $participant->forceFill([
                'participant_name' => $data['participant_name'] ?? $participant->participant_name,
                'participant_type' => $data['participant_type'] ?? $participant->participant_type,
                'institution' => $data['institution'] ?? $participant->institution,
            ])->save();

            $response = SurveyReadabilityResponse::updateOrCreate([
                'survey_readability_participant_id' => $participant->getKey(),
            ], [
                'survey_readability_round_id' => $participant->survey_readability_round_id,
                'survey_id' => $survey->getKey(),
                'overall_clarity_score' => (int) $data['overall_clarity_score'],
                'overall_length_score' => (int) $data['overall_length_score'],
                'terminology_clarity_score' => (int) $data['terminology_clarity_score'],
                'answer_option_clarity_score' => (int) $data['answer_option_clarity_score'],
                'instruction_clarity_score' => (int) $data['instruction_clarity_score'],
                'estimated_completion_minutes' => $data['estimated_completion_minutes'] ?? null,
                'has_confusing_items' => $feedbackRows !== [] || filled($data['confusing_items'] ?? null),
                'confusing_items' => $data['confusing_items'] ?? null,
                'general_comments' => $data['general_comments'] ?? null,
                'revision_suggestions' => $data['revision_suggestions'] ?? null,
                'final_decision' => $data['final_decision'],
            ]);

            $response->questionFeedback()->delete();
            SurveyReadabilityRevision::query()
                ->where('source_response_id', $response->getKey())
                ->delete();

            foreach ($feedbackRows as $feedback) {
                $question = $this->questionForSurvey($survey->getKey(), $feedback['survey_question_id'] ?? null);

                $questionFeedback = SurveyReadabilityQuestionFeedback::create([
                    'survey_readability_response_id' => $response->getKey(),
                    'survey_question_id' => $question?->getKey(),
                    'survey_page_id' => $question?->page_id,
                    'issue_type' => $feedback['issue_type'] ?? null,
                    'comment' => $feedback['comment'] ?? null,
                ]);

                if (filled($questionFeedback->comment)) {
                    SurveyReadabilityRevision::create([
                        'survey_id' => $survey->getKey(),
                        'survey_question_id' => $question?->getKey(),
                        'source_response_id' => $response->getKey(),
                        'issue_summary' => $this->issueSummary($question, $questionFeedback),
                        'status' => SurveyReadabilityRevision::STATUS_PENDING,
                    ]);
                }
            }

            if (filled($response->revision_suggestions)) {
                SurveyReadabilityRevision::create([
                    'survey_id' => $survey->getKey(),
                    'source_response_id' => $response->getKey(),
                    'issue_summary' => (string) $response->revision_suggestions,
                    'status' => SurveyReadabilityRevision::STATUS_PENDING,
                ]);
            }

            if (filled($response->confusing_items)) {
                SurveyReadabilityRevision::create([
                    'survey_id' => $survey->getKey(),
                    'source_response_id' => $response->getKey(),
                    'issue_summary' => (string) $response->confusing_items,
                    'status' => SurveyReadabilityRevision::STATUS_PENDING,
                ]);
            }

            $participant->markSubmitted();

            $this->activityLogger->log('survey_readability_response.submitted', null, $survey->project, $response, [
                'survey_readability_round_id' => $participant->survey_readability_round_id,
                'survey_readability_participant_id' => $participant->getKey(),
                'survey_readability_response_id' => $response->getKey(),
                'survey_id' => $survey->getKey(),
                'research_project_id' => $survey->project_id,
                'final_decision' => $response->final_decision,
            ], $request);

            return $response;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedFeedback(mixed $feedback): array
    {
        if (! is_array($feedback)) {
            return [];
        }

        return collect($feedback)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'survey_question_id' => $row['survey_question_id'] ?? null,
                'issue_type' => $row['issue_type'] ?? null,
                'comment' => $row['comment'] ?? null,
            ])
            ->filter(fn (array $row): bool => filled($row['survey_question_id']) || filled($row['issue_type']) || filled($row['comment']))
            ->values()
            ->all();
    }

    private function questionForSurvey(string $surveyId, ?string $questionId): ?SurveyQuestion
    {
        if (blank($questionId)) {
            return null;
        }

        return SurveyQuestion::query()
            ->where('survey_id', $surveyId)
            ->whereKey($questionId)
            ->first();
    }

    private function issueSummary(?SurveyQuestion $question, SurveyReadabilityQuestionFeedback $feedback): string
    {
        $issue = SurveyReadabilityQuestionFeedback::ISSUE_LABELS[$feedback->issue_type] ?? ($feedback->issue_type ?: 'Readability issue');
        $prefix = $question ? $question->label.' - '.$issue : $issue;

        return trim($prefix.': '.$feedback->comment);
    }
}
