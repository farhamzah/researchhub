<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\DTOs\SurveyResponseData;
use App\Modules\Surveys\Services\RespondentIdentityService;
use App\Modules\Surveys\Services\SurveyAnswerValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitSurveyResponseAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyAnswerValidationService $answerValidation,
        private readonly RespondentIdentityService $identityService,
    ) {}

    public function handle(Survey $survey, SurveyResponseData $data, ?Request $request = null): SurveyResponse
    {
        $survey->loadMissing(['project', 'questions', 'respondents']);

        if (! $survey->canReceiveResponses()) {
            $this->logRejected($survey, 'survey_unavailable', $request);

            throw ValidationException::withMessages([
                'survey' => 'This survey is not accepting responses.',
            ]);
        }

        try {
            $validatedAnswers = $this->answerValidation->validate($survey, $data->answers);
        } catch (ValidationException $exception) {
            $this->logRejected($survey, 'validation_failed', $request);

            throw $exception;
        }

        return DB::transaction(function () use ($survey, $data, $validatedAnswers, $request): SurveyResponse {
            $respondent = $this->identityService->createForSurvey($survey, $data->identity);

            $response = SurveyResponse::create([
                'survey_id' => $survey->getKey(),
                'respondent_id' => $respondent?->getKey(),
                'status' => SurveyResponse::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => [
                    'identity_mode' => $survey->identity_mode,
                    'answer_count' => count($validatedAnswers),
                ],
            ]);

            foreach ($survey->questions as $question) {
                if (! array_key_exists($question->question_key, $validatedAnswers)) {
                    continue;
                }

                $response->answers()->create([
                    'survey_question_id' => $question->getKey(),
                    'question_key' => $question->question_key,
                    'answer_value' => $validatedAnswers[$question->question_key],
                    'score' => null,
                ]);
            }

            $this->activityLogger->log('survey.response_submitted', null, $survey->project, $response, [
                'survey_id' => $survey->getKey(),
                'response_id' => $response->getKey(),
                'identity_mode' => $survey->identity_mode,
                'answer_count' => count($validatedAnswers),
                'has_respondent' => $respondent !== null,
            ], $request);

            return $response->load('answers');
        });
    }

    private function logRejected(Survey $survey, string $reason, ?Request $request): void
    {
        $this->activityLogger->log('survey.response_rejected', null, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'reason' => $reason,
            'status' => $survey->status,
            'is_public' => $survey->is_public,
        ], $request);
    }
}
