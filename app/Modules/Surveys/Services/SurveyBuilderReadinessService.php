<?php

namespace App\Modules\Surveys\Services;

use App\Models\AnalysisResult;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveyValidationAssignment;
use Illuminate\Support\Collection;

class SurveyBuilderReadinessService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey): array
    {
        $questions = $survey->questions;
        $analysis = $survey->analysisResults->sortByDesc('created_at')->first();
        $round = $survey->validationRounds->sortByDesc('created_at')->first();
        $submittedResponses = $this->submittedResponseCount($survey);

        return [
            'steps' => $this->steps($survey, $analysis, $round),
            'setup' => $this->setup($survey, $analysis, $round, $submittedResponses),
            'indicators' => $this->indicators($survey, $analysis),
            'questions' => $questions->values()->map(fn (SurveyQuestion $question, int $index): array => $this->questionCard($question, $index))->all(),
            'scoring' => $this->scoring($survey),
            'preview' => $questions->values()->map(fn (SurveyQuestion $question, int $index): array => $this->previewQuestion($question, $index))->all(),
            'validation' => $this->validation($survey, $round, $analysis),
            'responses' => $this->responses($survey, $analysis, $submittedResponses),
        ];
    }

    /**
     * @return array<int, array{label: string, anchor: string, status: string}>
     */
    private function steps(Survey $survey, ?AnalysisResult $analysis, mixed $round): array
    {
        $questions = $survey->questions;
        $scoredQuestions = $questions->filter(fn (SurveyQuestion $question): bool => (bool) $question->scoring?->is_scored);

        return [
            ['label' => 'Setup Survey', 'anchor' => 'setup-survey', 'status' => filled($survey->title) && filled($survey->description) ? 'Siap' : 'Perlu dilengkapi'],
            ['label' => 'Indikator', 'anchor' => 'indikator', 'status' => $survey->indicators->isNotEmpty() ? 'Siap' : 'Belum ada'],
            ['label' => 'Pertanyaan', 'anchor' => 'pertanyaan', 'status' => $questions->isNotEmpty() ? $questions->count().' item' : 'Belum ada'],
            ['label' => 'Skoring', 'anchor' => 'skoring', 'status' => $scoredQuestions->isNotEmpty() && $scoredQuestions->every(fn (SurveyQuestion $question): bool => $question->scoring?->indicator !== null) ? 'Siap' : 'Perlu perhatian'],
            ['label' => 'Preview', 'anchor' => 'preview', 'status' => $questions->isNotEmpty() ? 'Tersedia' : 'Kosong'],
            ['label' => 'Validasi Ahli', 'anchor' => 'validasi-ahli', 'status' => $round ? 'Ada ronde' : 'Belum ada'],
            ['label' => 'Respons & Analisis', 'anchor' => 'respons-analisis', 'status' => $analysis ? 'Ada hasil' : 'Belum dianalisis'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function setup(Survey $survey, ?AnalysisResult $analysis, mixed $round, int $submittedResponses): array
    {
        return [
            'question_count' => $survey->questions->count(),
            'response_count' => $this->officialResponseCount($survey),
            'submitted_response_count' => $submittedResponses,
            'validation_status' => $round
                ? sprintf('%s, %d validator submitted', $this->label((string) $round->status), $round->assignments->where('status', SurveyValidationAssignment::STATUS_SUBMITTED)->count())
                : 'Belum ada ronde validasi',
            'analysis_status' => $analysis ? ($analysis->title ?: 'Analysis result available') : 'Belum ada hasil analisis',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indicators(Survey $survey, ?AnalysisResult $analysis): array
    {
        $averages = collect($analysis?->result_payload['indicator_summary'] ?? [])
            ->mapWithKeys(fn (array $row): array => [(string) ($row['indicator_name'] ?? '') => $row['mean'] ?? null]);

        return $survey->indicators
            ->values()
            ->map(fn ($indicator): array => [
                'name' => $indicator->name,
                'description' => $indicator->description,
                'scale' => $indicator->scale?->name,
                'linked_question_count' => $indicator->questionScorings->pluck('survey_question_id')->filter()->unique()->count(),
                'average_score' => $averages->get($indicator->name),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function questionCard(SurveyQuestion $question, int $index): array
    {
        $scoring = $question->scoring;

        return [
            'id' => $question->id,
            'order_label' => 'Question '.($index + 1).' / Order '.$question->sort_order,
            'type' => $question->type,
            'type_label' => $this->label($question->type),
            'label' => $question->label,
            'help_text' => $question->help_text,
            'is_required' => $question->is_required,
            'page' => $question->page?->title,
            'indicator' => $scoring?->indicator?->name,
            'is_scored' => (bool) $scoring?->is_scored,
            'option_count' => $this->optionCount($question),
            'options_preview' => $this->optionLabels($question),
            'scoring_status' => $this->scoringStatus($question),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoring(Survey $survey): array
    {
        $scoreableTypes = [
            SurveyQuestion::TYPE_SINGLE_CHOICE,
            SurveyQuestion::TYPE_MULTIPLE_CHOICE,
            SurveyQuestion::TYPE_LIKERT,
            SurveyQuestion::TYPE_NUMBER,
        ];

        $rows = $survey->questions
            ->values()
            ->map(function (SurveyQuestion $question): array {
                $scoring = $question->scoring;
                $status = $this->scoringStatus($question);

                return [
                    'question' => $question->label,
                    'type' => $this->label($question->type),
                    'indicator' => $scoring?->indicator?->name,
                    'scale' => $scoring?->indicator?->scale?->name,
                    'score_range' => $scoring && ($scoring->score_min !== null || $scoring->score_max !== null)
                        ? trim((string) $scoring->score_min).' - '.trim((string) $scoring->score_max)
                        : 'Not configured',
                    'weight' => $scoring?->weight,
                    'status' => $status,
                ];
            });

        return [
            'total_scoreable' => $rows->whereIn('status', ['Configured', 'Missing indicator', 'Missing scale/range'])->count(),
            'configured' => $rows->where('status', 'Configured')->count(),
            'with_indicator' => $rows->filter(fn (array $row): bool => filled($row['indicator']))->count(),
            'missing' => $rows->whereIn('status', ['Missing indicator', 'Missing scale/range'])->count(),
            'indicators_used' => $rows->pluck('indicator')->filter()->unique()->count(),
            'rows' => $rows->all(),
        ];
    }

    private function scoringStatus(SurveyQuestion $question): string
    {
        $scoring = $question->scoring;

        if (in_array($question->type, [
            SurveyQuestion::TYPE_SHORT_TEXT,
            SurveyQuestion::TYPE_LONG_TEXT,
            SurveyQuestion::TYPE_DATE,
            SurveyQuestion::TYPE_CONSENT,
            SurveyQuestion::TYPE_HIDDEN,
        ], true)) {
            return $scoring && ! $scoring->is_scored ? 'Descriptive' : 'Not scoreable';
        }

        if ($question->type === SurveyQuestion::TYPE_LIKERT_MATRIX) {
            return 'Not scoreable';
        }

        if (! $scoring || ! $scoring->is_scored) {
            return 'Descriptive';
        }

        if (! $scoring->indicator) {
            return 'Missing indicator';
        }

        if ($scoring->indicator->scale && ($scoring->score_min === null || $scoring->score_max === null)) {
            return 'Missing scale/range';
        }

        return 'Configured';
    }

    /**
     * @return array<string, mixed>
     */
    private function previewQuestion(SurveyQuestion $question, int $index): array
    {
        return [
            'number' => $index + 1,
            'type' => $question->type,
            'type_label' => $this->label($question->type),
            'label' => $question->label,
            'help_text' => $question->help_text,
            'is_required' => $question->is_required,
            'options' => $this->optionLabels($question),
            'placeholder' => match ($question->type) {
                SurveyQuestion::TYPE_LONG_TEXT => 'Tulis jawaban panjang di sini.',
                SurveyQuestion::TYPE_NUMBER => 'Masukkan angka.',
                SurveyQuestion::TYPE_DATE => 'Pilih tanggal.',
                SurveyQuestion::TYPE_HIDDEN => 'Hidden field tidak ditampilkan kepada responden.',
                default => 'Tulis jawaban singkat di sini.',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validation(Survey $survey, mixed $round, ?AnalysisResult $analysis): array
    {
        $likertQuestions = $survey->questions->filter(fn (SurveyQuestion $question): bool => in_array($question->type, [
            SurveyQuestion::TYPE_LIKERT,
            SurveyQuestion::TYPE_LIKERT_MATRIX,
        ], true));
        $scoredQuestions = $survey->questions->filter(fn (SurveyQuestion $question): bool => (bool) $question->scoring?->is_scored);

        $submittedAssignments = $round?->assignments->where('status', SurveyValidationAssignment::STATUS_SUBMITTED) ?? collect();
        $submittedScores = $submittedAssignments instanceof Collection
            ? $submittedAssignments->sum(fn ($assignment): int => $assignment->scores->count())
            : 0;

        $items = [
            ['label' => 'Survey has title', 'complete' => filled($survey->title)],
            ['label' => 'Survey has description', 'complete' => filled($survey->description)],
            ['label' => 'Survey has questions', 'complete' => $survey->questions->isNotEmpty()],
            ['label' => 'Likert questions have options', 'complete' => $likertQuestions->isNotEmpty() && $likertQuestions->every(fn (SurveyQuestion $question): bool => $this->optionCount($question) > 0)],
            ['label' => 'Scored questions have scoring', 'complete' => $scoredQuestions->isNotEmpty() && $scoredQuestions->every(fn (SurveyQuestion $question): bool => $question->scoring?->indicator !== null)],
            ['label' => 'Indicators exist', 'complete' => $survey->indicators->isNotEmpty()],
            ['label' => 'Validator round exists', 'complete' => (bool) $round],
            ['label' => 'Submitted validation exists', 'complete' => $submittedAssignments instanceof Collection && $submittedAssignments->isNotEmpty()],
            ['label' => 'Aiken/CVI result exists', 'complete' => $submittedScores > 0],
        ];

        return [
            'round_id' => $round?->id,
            'round_title' => $round?->title,
            'submitted_assignments' => $submittedAssignments instanceof Collection ? $submittedAssignments->count() : 0,
            'submitted_scores' => $submittedScores,
            'analysis_title' => $analysis?->title,
            'items' => $items,
            'complete_count' => collect($items)->where('complete', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responses(Survey $survey, ?AnalysisResult $analysis, int $submittedResponses): array
    {
        $officialResponses = $survey->responses
            ->where('is_test_response', false)
            ->where('excluded_from_analysis', false);
        $lastResponse = $officialResponses->sortByDesc('submitted_at')->first();

        return [
            'response_count' => $officialResponses->count(),
            'submitted_count' => $submittedResponses,
            'last_response_at' => $lastResponse?->submitted_at?->format('Y-m-d H:i'),
            'analysis_title' => $analysis?->title,
            'analysis_summary' => $analysis?->summary ?? [],
        ];
    }

    private function submittedResponseCount(Survey $survey): int
    {
        return $survey->responses
            ->where('status', SurveyResponse::STATUS_SUBMITTED)
            ->where('is_test_response', false)
            ->where('excluded_from_analysis', false)
            ->count();
    }

    private function officialResponseCount(Survey $survey): int
    {
        return $survey->responses
            ->where('is_test_response', false)
            ->where('excluded_from_analysis', false)
            ->count();
    }

    private function optionCount(SurveyQuestion $question): int
    {
        return count($this->optionLabels($question));
    }

    /**
     * @return array<int, string>
     */
    private function optionLabels(SurveyQuestion $question): array
    {
        $options = $question->options ?? [];
        $settings = $question->settings ?? [];

        $values = match ($question->type) {
            SurveyQuestion::TYPE_SINGLE_CHOICE, SurveyQuestion::TYPE_MULTIPLE_CHOICE => $options['choices'] ?? $options['options'] ?? [],
            SurveyQuestion::TYPE_LIKERT => $settings['scale'] ?? $options['scale'] ?? $options['labels'] ?? [],
            SurveyQuestion::TYPE_LIKERT_MATRIX => $options['columns'] ?? $settings['scale'] ?? [],
            default => [],
        };

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn (mixed $value, mixed $key): string => is_array($value)
                ? trim((string) ($value['value'] ?? '').(filled($value['label'] ?? null) ? ' — '.(string) $value['label'] : ''))
                : (is_string($key) && ! is_numeric($key) ? $key.'. '.$value : (string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function label(string $value): string
    {
        return str($value ?: 'not_set')->replace('_', ' ')->title()->toString();
    }
}
