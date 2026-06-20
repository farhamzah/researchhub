<?php

namespace App\Modules\SupervisorReviews\Services;

use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveySupervisorReviewRound;

class SupervisorReviewSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Survey $survey): array
    {
        $survey->loadMissing([
            'project',
            'pages.questions.scoring.indicator',
            'questions.scoring.indicator',
        ]);

        return [
            'survey' => [
                'id' => $survey->getKey(),
                'title' => $survey->title,
                'description' => $survey->description,
                'instrument_type' => $survey->instrument_type,
                'intro_title' => $survey->intro_title,
                'intro_text' => $survey->intro_text,
                'intro_image_path' => $survey->intro_image_path,
                'intro_image_caption' => $survey->intro_image_caption,
                'privacy_statement' => $survey->privacy_statement,
                'respondent_instruction' => $survey->respondent_instruction,
                'consent_text' => $survey->consent_text,
                'updated_at' => $survey->updated_at?->toISOString(),
            ],
            'pages' => $survey->pages
                ->map(fn (SurveyPage $page): array => [
                    'id' => $page->getKey(),
                    'title' => $page->title,
                    'description' => $page->description,
                    'sort_order' => $page->sort_order,
                    'questions' => $page->questions
                        ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
                        ->sortBy('sort_order')
                        ->map(fn (SurveyQuestion $question): array => $this->questionSnapshot($question))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'questions_without_page' => $survey->questions
                ->whereNull('page_id')
                ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
                ->sortBy('sort_order')
                ->map(fn (SurveyQuestion $question): array => $this->questionSnapshot($question))
                ->values()
                ->all(),
            'snapshot_taken_at' => now()->toISOString(),
        ];
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function ensureSnapshot(SurveySupervisorReviewRound $round): SurveySupervisorReviewRound
    {
        if ($round->snapshot_json !== null && filled($round->snapshot_hash)) {
            return $round;
        }

        $snapshot = $this->snapshot($round->survey);

        $round->forceFill([
            'snapshot_json' => $snapshot,
            'snapshot_hash' => $this->hash($snapshot),
            'snapshot_taken_at' => now(),
        ])->save();

        return $round->refresh();
    }

    public function instrumentChanged(SurveySupervisorReviewRound $round): bool
    {
        if (blank($round->snapshot_hash)) {
            return false;
        }

        return $round->snapshot_hash !== $this->hash($this->snapshot($round->survey));
    }

    private function questionSnapshot(SurveyQuestion $question): array
    {
        return [
            'id' => $question->getKey(),
            'question_key' => $question->question_key,
            'label' => $question->label,
            'type' => $question->type,
            'is_required' => (bool) $question->is_required,
            'options' => $question->options ?? [],
            'settings' => $question->settings ?? [],
            'scoring' => $question->scoring ? [
                'indicator' => $question->scoring->indicator?->name,
                'is_scored' => (bool) $question->scoring->is_scored,
                'score_direction' => $question->scoring->score_direction,
                'settings' => $question->scoring->settings ?? [],
            ] : null,
        ];
    }
}
