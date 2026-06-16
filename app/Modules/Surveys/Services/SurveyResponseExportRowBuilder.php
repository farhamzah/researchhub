<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;

class SurveyResponseExportRowBuilder
{
    public function __construct(private readonly RespondentPrivacyService $privacyService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(SurveyResponse $response, ?User $viewer = null, bool $withIdentity = false): array
    {
        $response->loadMissing(['survey.project', 'respondent', 'answers.question']);

        $survey = $response->survey;
        $respondentDisplay = $this->privacyService->display($response->respondent, $survey, $viewer);
        $row = [
            'response_id' => $response->getKey(),
            'survey_id' => $survey->getKey(),
            'respondent' => $respondentDisplay,
            'respondent_display' => $respondentDisplay,
            'pseudonym_code' => $response->respondent?->pseudonym_code,
            'status' => $response->status,
            'submitted_at' => $response->submitted_at?->toISOString(),
        ];

        if ($withIdentity && $this->privacyService->canViewFullIdentity($viewer, $survey)) {
            foreach ($this->privacyService->identityFields($response->respondent, $survey, $viewer) as $key => $value) {
                $row["identity_{$key}"] = $value;
                $row["respondent_{$key}"] = $value;
            }
        }

        foreach ($response->answers as $answer) {
            if ($answer->question?->type === 'hidden') {
                continue;
            }

            $row[$answer->question_key] = $answer->answer_value;
        }

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildForSurvey(Survey $survey, ?User $viewer = null, bool $withIdentity = false): array
    {
        return $survey->responses()
            ->official()
            ->with(['survey.project', 'respondent', 'answers.question'])
            ->latest('submitted_at')
            ->get()
            ->map(fn (SurveyResponse $response): array => $this->build($response, $viewer, $withIdentity))
            ->all();
    }
}
