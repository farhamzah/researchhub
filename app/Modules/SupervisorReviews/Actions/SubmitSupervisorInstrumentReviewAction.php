<?php

namespace App\Modules\SupervisorReviews\Actions;

use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewRevision;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SubmitSupervisorInstrumentReviewAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SurveySupervisorReviewer $reviewer, array $data, ?Request $request = null): SurveySupervisorReviewer
    {
        $reviewer->loadMissing('round.survey.project');

        if (! $reviewer->isAccessible()) {
            throw ValidationException::withMessages([
                'review' => 'This supervisor review link is no longer available.',
            ]);
        }

        $comments = collect(Arr::wrap($data['comments'] ?? []))
            ->filter(fn (mixed $comment): bool => is_array($comment) && filled($comment['comment'] ?? null));

        if ($comments->isEmpty() && blank($data['final_notes'] ?? null)) {
            throw ValidationException::withMessages([
                'comments' => 'Add at least one comment or final note before submitting.',
            ]);
        }

        $comments->each(function (array $comment) use ($reviewer): void {
            $stored = SurveySupervisorReviewComment::create([
                'survey_supervisor_reviewer_id' => $reviewer->getKey(),
                'survey_supervisor_review_round_id' => $reviewer->survey_supervisor_review_round_id,
                'survey_question_id' => $comment['survey_question_id'] ?? null,
                'comment_type' => $comment['comment_type'],
                'target_key' => $comment['target_key'] ?? null,
                'target_label' => $comment['target_label'] ?? null,
                'comment' => $comment['comment'],
                'suggested_revision' => $comment['suggested_revision'] ?? null,
                'severity' => $comment['severity'] ?? null,
                'decision' => $comment['decision'] ?? null,
            ]);

            SurveySupervisorReviewRevision::create([
                'survey_id' => $reviewer->round->survey_id,
                'survey_supervisor_review_round_id' => $reviewer->survey_supervisor_review_round_id,
                'survey_supervisor_reviewer_id' => $reviewer->getKey(),
                'survey_supervisor_review_comment_id' => $stored->getKey(),
                'item_label' => $this->itemLabel($stored),
                'supervisor_code' => $reviewer->supervisor_code ?: $reviewer->supervisor_name,
                'comment' => $stored->comment,
                'suggested_revision' => $stored->suggested_revision,
                'severity' => $stored->severity,
                'status' => SurveySupervisorReviewRevision::STATUS_PENDING,
            ]);
        });

        $reviewer->markSubmitted($data['final_decision'], $data['final_notes'] ?? null);

        $this->activityLogger->log('survey_supervisor_review.submitted', null, $reviewer->round->project, $reviewer, [
            'survey_supervisor_review_round_id' => $reviewer->survey_supervisor_review_round_id,
            'survey_supervisor_reviewer_id' => $reviewer->getKey(),
            'survey_id' => $reviewer->round->survey_id,
            'research_project_id' => $reviewer->round->research_project_id,
            'status' => $reviewer->status,
            'comment_count' => $comments->count(),
        ], $request);

        return $reviewer->refresh();
    }

    private function itemLabel(SurveySupervisorReviewComment $comment): string
    {
        return collect([$comment->target_key, $comment->target_label])
            ->filter()
            ->join(' - ') ?: ucfirst($comment->comment_type);
    }
}
