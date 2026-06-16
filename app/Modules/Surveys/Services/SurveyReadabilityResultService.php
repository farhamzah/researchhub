<?php

namespace App\Modules\Surveys\Services;

use App\Models\SurveyQuestion;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityQuestionFeedback;
use App\Models\SurveyReadabilityRevision;
use App\Models\SurveyReadabilityRound;
use Illuminate\Support\Collection;

class SurveyReadabilityResultService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(SurveyReadabilityRound $round): array
    {
        $round->load([
            'survey.project',
            'survey.questions',
            'survey.readabilityRevisions.question',
            'survey.readabilityRevisions.sourceResponse.participant',
            'participants.response.questionFeedback.question',
            'creator',
        ]);

        $submittedParticipants = $round->participants
            ->filter(fn (SurveyReadabilityParticipant $participant): bool => $participant->isSubmitted() && $participant->response !== null)
            ->values();

        $responses = $submittedParticipants
            ->map(fn (SurveyReadabilityParticipant $participant) => $participant->response)
            ->filter()
            ->values();

        $feedback = $responses
            ->flatMap(fn ($response): Collection => $response->questionFeedback)
            ->values();

        return [
            'round' => $round,
            'summary' => $this->summary($round, $submittedParticipants, $responses, $feedback),
            'participants' => $this->participants($round),
            'issue_type_counts' => $this->issueTypeCounts($feedback),
            'decision_counts' => $responses->pluck('final_decision')->filter()->countBy()->all(),
            'flagged_questions' => $this->flaggedQuestions($feedback),
            'comments' => $this->comments($responses),
            'revision_matrix' => $this->revisionMatrix($round),
            'narrative' => $this->narrative($round, $responses),
        ];
    }

    private function summary(SurveyReadabilityRound $round, Collection $submittedParticipants, Collection $responses, Collection $feedback): array
    {
        $overallAverage = $this->average($responses->flatMap(fn ($response): array => [
            $response->overall_clarity_score,
            $response->overall_length_score,
            $response->terminology_clarity_score,
            $response->answer_option_clarity_score,
            $response->instruction_clarity_score,
        ]));

        return [
            'round_count' => $round->survey->readabilityRounds()->count(),
            'participant_count' => $round->participants->count(),
            'submitted_count' => $submittedParticipants->count(),
            'pending_count' => $round->participants->filter(fn (SurveyReadabilityParticipant $participant): bool => ! $participant->isSubmitted() && ! $participant->isRevoked())->count(),
            'question_count' => $round->survey->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->count(),
            'average_readability_score' => $overallAverage,
            'category' => $this->category($overallAverage),
            'average_overall_clarity_score' => $this->average($responses->pluck('overall_clarity_score')),
            'average_instruction_clarity_score' => $this->average($responses->pluck('instruction_clarity_score')),
            'average_terminology_clarity_score' => $this->average($responses->pluck('terminology_clarity_score')),
            'average_answer_option_clarity_score' => $this->average($responses->pluck('answer_option_clarity_score')),
            'average_length_score' => $this->average($responses->pluck('overall_length_score')),
            'average_estimated_completion_minutes' => $this->average($responses->pluck('estimated_completion_minutes')),
            'confusing_item_count' => $feedback->count(),
            'has_preliminary_results' => $submittedParticipants->count() > 0 && $submittedParticipants->count() < $round->participants->count(),
        ];
    }

    private function participants(SurveyReadabilityRound $round): array
    {
        return $round->participants
            ->map(fn (SurveyReadabilityParticipant $participant): array => [
                'id' => $participant->getKey(),
                'participant_name' => $participant->participant_name,
                'participant_type' => $participant->participant_type,
                'institution' => $participant->institution,
                'status' => $participant->status,
                'opened_at' => $participant->opened_at,
                'submitted_at' => $participant->submitted_at,
                'revoked_at' => $participant->revoked_at,
                'has_token' => filled($participant->token_hash),
                'average_score' => $participant->response ? $this->average(collect([
                    $participant->response->overall_clarity_score,
                    $participant->response->overall_length_score,
                    $participant->response->terminology_clarity_score,
                    $participant->response->answer_option_clarity_score,
                    $participant->response->instruction_clarity_score,
                ])) : null,
                'final_decision' => $participant->response?->final_decision,
            ])
            ->values()
            ->all();
    }

    private function issueTypeCounts(Collection $feedback): array
    {
        return $feedback
            ->pluck('issue_type')
            ->filter()
            ->countBy()
            ->all();
    }

    private function flaggedQuestions(Collection $feedback): array
    {
        return $feedback
            ->filter(fn (SurveyReadabilityQuestionFeedback $item): bool => $item->question !== null)
            ->groupBy('survey_question_id')
            ->map(function (Collection $items): array {
                $first = $items->first();

                return [
                    'question_id' => $first->survey_question_id,
                    'question_text' => $first->question?->label,
                    'count' => $items->count(),
                    'issue_counts' => $items->pluck('issue_type')->filter()->countBy()->all(),
                    'comments' => $items->pluck('comment')->filter()->values()->all(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function comments(Collection $responses): array
    {
        return $responses
            ->map(fn ($response): array => [
                'participant_name' => $response->participant?->participant_name ?: 'Anonymous participant',
                'participant_type' => $response->participant?->participant_type,
                'general_comments' => $response->general_comments,
                'revision_suggestions' => $response->revision_suggestions,
                'confusing_items' => $response->confusing_items,
                'final_decision' => $response->final_decision,
            ])
            ->filter(fn (array $row): bool => filled($row['general_comments']) || filled($row['revision_suggestions']) || filled($row['confusing_items']))
            ->values()
            ->all();
    }

    private function revisionMatrix(SurveyReadabilityRound $round): array
    {
        return $round->survey->readabilityRevisions
            ->map(fn (SurveyReadabilityRevision $revision): array => [
                'id' => $revision->getKey(),
                'question_number' => $this->questionNumber($round, $revision->question),
                'question_text' => $revision->question?->label,
                'issue_summary' => $revision->issue_summary,
                'participant_name' => $revision->sourceResponse?->participant?->participant_name ?: 'Participant',
                'revision_action' => $revision->revision_action,
                'status' => $revision->status,
                'researcher_note' => $revision->researcher_note,
            ])
            ->values()
            ->all();
    }

    private function questionNumber(SurveyReadabilityRound $round, ?SurveyQuestion $question): ?int
    {
        if (! $question) {
            return null;
        }

        $index = $round->survey->questions
            ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
            ->sortBy('sort_order')
            ->values()
            ->search(fn (SurveyQuestion $candidate): bool => $candidate->getKey() === $question->getKey());

        return $index === false ? null : $index + 1;
    }

    private function category(?float $average): string
    {
        if ($average === null) {
            return 'No submitted readability test yet';
        }

        return match (true) {
            $average >= 4.20 => 'Very readable',
            $average >= 3.40 => 'Readable with minor revision',
            $average >= 2.60 => 'Needs revision',
            $average >= 1.80 => 'Low readability',
            default => 'Not readable',
        };
    }

    private function narrative(SurveyReadabilityRound $round, Collection $responses): string
    {
        $average = $this->average($responses->flatMap(fn ($response): array => [
            $response->overall_clarity_score,
            $response->overall_length_score,
            $response->terminology_clarity_score,
            $response->answer_option_clarity_score,
            $response->instruction_clarity_score,
        ]));

        if ($average === null) {
            return 'Belum terdapat hasil uji keterbacaan untuk instrumen '.$round->survey->title.'.';
        }

        return sprintf(
            'Uji keterbacaan instrumen %s melibatkan %d peserta pilot yang telah mengirimkan umpan balik. Rata-rata skor keterbacaan adalah %.2f dengan kategori %s. Masukan peserta digunakan untuk mengidentifikasi redaksi yang kurang jelas, istilah yang sulit dipahami, opsi jawaban yang membingungkan, dan kebutuhan revisi sebelum instrumen disebarkan lebih luas.',
            $round->survey->title,
            $responses->count(),
            $average,
            $this->category($average),
        );
    }

    private function average(Collection $values): ?float
    {
        $filtered = $values
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);

        return $filtered->isEmpty() ? null : round($filtered->average(), 2);
    }
}
