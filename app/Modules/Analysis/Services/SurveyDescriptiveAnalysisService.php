<?php

namespace App\Modules\Analysis\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Support\Collection;

class SurveyDescriptiveAnalysisService
{
    public function __construct(private readonly QuestionDescriptiveAnalyzer $questionAnalyzer) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(Survey $survey): array
    {
        $survey->loadMissing(['project', 'questions']);

        $submittedResponses = $survey->responses()
            ->where('status', SurveyResponse::STATUS_SUBMITTED)
            ->with('answers.question')
            ->get();

        $submittedResponseIds = $submittedResponses->pluck('id');
        $answersByQuestion = SurveyAnswer::query()
            ->whereIn('survey_response_id', $submittedResponseIds)
            ->with('question')
            ->get()
            ->groupBy('survey_question_id');

        $questions = $survey->questions()
            ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
            ->orderBy('sort_order')
            ->get();

        $questionSummaries = $questions
            ->map(fn (SurveyQuestion $question): array => $this->questionAnalyzer->analyze(
                $question,
                $answersByQuestion->get($question->id, collect()),
                $submittedResponses->count(),
            ))
            ->values();

        return [
            'survey' => [
                'id' => $survey->getKey(),
                'title' => $survey->title,
                'status' => $survey->status,
                'identity_mode' => $survey->identity_mode,
            ],
            'summary' => [
                'response_count' => $survey->responses()->count(),
                'submitted_count' => $submittedResponses->count(),
                'completion_count' => $submittedResponses->count(),
                'analyzed_question_count' => $questionSummaries->count(),
                'hidden_question_count' => $survey->questions()
                    ->where('type', SurveyQuestion::TYPE_HIDDEN)
                    ->count(),
            ],
            'questions' => $questionSummaries->all(),
            'tables' => [
                $this->questionSummaryTable($questionSummaries),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $questionSummaries
     * @return array{title: string, table_key: string, columns: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function questionSummaryTable(Collection $questionSummaries): array
    {
        return [
            'title' => 'Question Descriptive Summary',
            'table_key' => 'question_descriptive_summary',
            'columns' => [
                'question_key',
                'type',
                'answered_count',
                'missing_count',
                'mean',
                'median',
                'min',
                'max',
                'standard_deviation',
            ],
            'rows' => $questionSummaries
                ->map(fn (array $summary): array => [
                    'question_key' => $summary['question_key'],
                    'type' => $summary['type'],
                    'answered_count' => $summary['answered_count'],
                    'missing_count' => $summary['missing_count'],
                    'mean' => $summary['mean'] ?? null,
                    'median' => $summary['median'] ?? null,
                    'min' => $summary['min'] ?? null,
                    'max' => $summary['max'] ?? null,
                    'standard_deviation' => $summary['standard_deviation'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }
}
