<?php

namespace App\Modules\SupervisorReviews\Actions;

use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewRevision;
use App\Models\SurveySupervisorReviewRound;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\SurveyInstrumentWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitSupervisorInstrumentReviewAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SurveyInstrumentWorkflowService $workflow,
    ) {}

    public function handle(SurveySupervisorReviewer $reviewer, array $data, ?Request $request = null, bool $viaHub = false): SurveySupervisorReviewer
    {
        $reviewer->loadMissing('round.survey.project', 'round.reviewers');
        if ($viaHub ? ! $reviewer->isReviewOpen() : ! $reviewer->isAccessible()) {
            throw ValidationException::withMessages(['review' => 'Tautan review pembimbing tidak lagi tersedia.']);
        }

        $comments = collect(Arr::wrap($data['comments'] ?? []))->filter(function (mixed $comment): bool {
            return is_array($comment) && (filled($comment['comment'] ?? null) || filled($comment['decision'] ?? null));
        });
        if ($comments->isEmpty() && blank($data['final_notes'] ?? null)) {
            throw ValidationException::withMessages(['comments' => 'Isi setidaknya satu komentar/keputusan item atau komentar umum.']);
        }

        return DB::transaction(function () use ($reviewer, $comments, $data, $request): SurveySupervisorReviewer {
            $comments->each(function (array $comment) use ($reviewer): void {
                $stored = SurveySupervisorReviewComment::create([
                    'survey_supervisor_reviewer_id' => $reviewer->getKey(),
                    'survey_supervisor_review_round_id' => $reviewer->survey_supervisor_review_round_id,
                    'survey_question_id' => $comment['survey_question_id'] ?? null,
                    'comment_type' => $comment['comment_type'],
                    'target_key' => $comment['target_key'] ?? null,
                    'target_label' => $comment['target_label'] ?? null,
                    'comment' => filled($comment['comment'] ?? null) ? $comment['comment'] : '—',
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
            $this->finalizeRoundWhenComplete($reviewer->round->refresh(), $reviewer);

            $this->activityLogger->log('survey_supervisor_review.submitted', null, $reviewer->round->project, $reviewer, [
                'survey_supervisor_review_round_id' => $reviewer->survey_supervisor_review_round_id,
                'survey_supervisor_reviewer_id' => $reviewer->getKey(),
                'survey_id' => $reviewer->round->survey_id,
                'research_project_id' => $reviewer->round->research_project_id,
                'instrument_version' => $reviewer->round->instrument_version,
                'final_decision' => $data['final_decision'],
                'status' => $reviewer->status,
                'comment_count' => $comments->count(),
            ], $request);

            return $reviewer->refresh();
        });
    }

    private function finalizeRoundWhenComplete(SurveySupervisorReviewRound $round, SurveySupervisorReviewer $reviewer): void
    {
        $survey = $round->survey()->firstOrFail();
        if (blank($survey->instrument_version)) {
            return;
        }
        if ($round->reviewers()->where('status', '!=', SurveySupervisorReviewer::STATUS_SUBMITTED)->exists()) {
            return;
        }

        $round->forceFill(['status' => SurveySupervisorReviewRound::STATUS_COMPLETED, 'finalized_at' => now(), 'closed_at' => now()])->save();
        if ($survey->workflow_state !== SurveyInstrumentWorkflowService::UNDER_SUPERVISOR_REVIEW) {
            return;
        }

        $needsRevision = $round->reviewers()->where('final_decision', SurveySupervisorReviewer::DECISION_REVISION_REQUIRED)->exists();
        $this->workflow->transition(
            $survey,
            $needsRevision ? SurveyInstrumentWorkflowService::SUPERVISOR_REVISION_REQUIRED : SurveyInstrumentWorkflowService::SUPERVISOR_APPROVED,
            null,
            $reviewer->final_notes,
            $reviewer->supervisor_name,
        );
    }

    private function itemLabel(SurveySupervisorReviewComment $comment): string
    {
        return collect([$comment->target_key, $comment->target_label])->filter()->join(' - ') ?: ucfirst($comment->comment_type);
    }
}
