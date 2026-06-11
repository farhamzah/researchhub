<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SurveyResponseCsvExporter
{
    private const BASE_HEADERS = [
        'response_id',
        'survey_id',
        'submitted_at',
        'status',
        'respondent_display',
        'pseudonym_code',
    ];

    private const IDENTITY_HEADERS = [
        'respondent_name',
        'respondent_email',
        'respondent_identifier',
        'respondent_institution',
    ];

    public function __construct(
        private readonly SurveyResponseExportRowBuilder $rowBuilder,
        private readonly RespondentPrivacyService $privacyService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function toCsv(Survey $survey, User $user, bool $withIdentity = false, ?Request $request = null): string
    {
        $survey->loadMissing('project');

        Gate::forUser($user)->authorize('exportResponses', $survey);

        if ($withIdentity && ! $this->privacyService->canViewFullIdentity($user, $survey)) {
            throw new AuthorizationException('You are not allowed to export respondent identity.');
        }

        $questions = $this->exportableQuestions($survey);
        $headers = $this->headers($questions, $withIdentity);
        $responses = $survey->responses()
            ->with(['survey.project', 'respondent', 'answers.question'])
            ->oldest('submitted_at')
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($responses as $response) {
            fputcsv($handle, $this->row($response, $user, $questions, $headers, $withIdentity));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->activityLogger->log('survey.responses_exported', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'response_count' => $responses->count(),
            'with_identity' => $withIdentity,
        ], $request);

        return $csv;
    }

    public function filename(Survey $survey, bool $withIdentity = false): string
    {
        $suffix = $withIdentity ? '-responses-with-identity.csv' : '-responses.csv';

        return "{$survey->slug}{$suffix}";
    }

    /**
     * @param  Collection<int, SurveyQuestion>  $questions
     * @return array<int, string>
     */
    private function headers(Collection $questions, bool $withIdentity): array
    {
        return [
            ...self::BASE_HEADERS,
            ...($withIdentity ? self::IDENTITY_HEADERS : []),
            ...$questions->pluck('question_key')->all(),
        ];
    }

    /**
     * @param  Collection<int, SurveyQuestion>  $questions
     * @param  array<int, string>  $headers
     * @return array<int, string|null>
     */
    private function row(SurveyResponse $response, User $user, Collection $questions, array $headers, bool $withIdentity): array
    {
        $row = $this->rowBuilder->build($response, $user, $withIdentity);
        $answers = $response->answers->keyBy('question_key');

        foreach ($questions as $question) {
            $answer = $answers->get($question->question_key);
            $row[$question->question_key] = $this->normalizeAnswerValue($answer, $question);
        }

        return collect($headers)
            ->map(fn (string $header): ?string => isset($row[$header]) ? (string) $row[$header] : null)
            ->all();
    }

    /**
     * @return Collection<int, SurveyQuestion>
     */
    private function exportableQuestions(Survey $survey): Collection
    {
        return $survey->questions()
            ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
            ->orderBy('sort_order')
            ->get();
    }

    private function normalizeAnswerValue(?SurveyAnswer $answer, SurveyQuestion $question): ?string
    {
        if (! $answer) {
            return null;
        }

        $value = $answer->answer_value;

        if ($value === null) {
            return null;
        }

        if ($question->type === SurveyQuestion::TYPE_CONSENT) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';
        }

        if ($question->type === SurveyQuestion::TYPE_MULTIPLE_CHOICE && is_array($value) && array_is_list($value)) {
            return implode(' | ', array_map(fn (mixed $item): string => (string) $item, $value));
        }

        if ($question->type === SurveyQuestion::TYPE_LIKERT_MATRIX && is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        if (is_array($value)) {
            return array_is_list($value)
                ? implode(' | ', array_map(fn (mixed $item): string => (string) $item, $value))
                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return (string) $value;
    }
}
