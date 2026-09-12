<?php

namespace App\Http\Controllers;

use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\SupervisorReviews\Actions\SubmitSupervisorInstrumentReviewAction;
use App\Modules\SupervisorReviews\Services\SupervisorReviewerHubTokenResolver;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicSupervisorReviewerHubController extends Controller
{
    public function show(string $token, Request $request, SupervisorReviewerHubTokenResolver $resolver): View|Response
    {
        $hub = $resolver->resolve($token, $request, true);
        if (! $hub) {
            abort(404);
        }
        if (! $hub->isAccessible()) {
            return response()->view('supervisor-review.unavailable', ['reviewer' => $hub], 403);
        }

        $reviewers = $hub->reviewers->sortBy(fn (SurveySupervisorReviewer $reviewer): string => $reviewer->round->survey->instrument_code);

        return view('supervisor-review.hub', compact('hub', 'reviewers', 'token'));
    }

    public function instrument(
        string $token,
        SurveySupervisorReviewer $reviewer,
        Request $request,
        SupervisorReviewerHubTokenResolver $resolver,
        SupervisorReviewSnapshotService $snapshots,
        ActivityLogger $activityLogger,
    ): View|Response {
        $hub = $resolver->resolve($token, $request, true);
        $this->authorizeAssignment($hub, $reviewer);

        if ($reviewer->isSubmitted()) {
            return view('supervisor-review.thank-you', [
                'reviewer' => $reviewer,
                'hubUrl' => route('supervisor-review.hub.show', compact('token')),
            ]);
        }
        if (! $reviewer->isReviewOpen()) {
            return response()->view('supervisor-review.unavailable', ['reviewer' => $reviewer], 403);
        }

        $wasOpened = $reviewer->opened_at !== null;
        $reviewer->markOpenedFromHub();
        if (! $wasOpened) {
            $activityLogger->log('survey_supervisor_review_hub_instrument.opened', null, $hub->project, $reviewer, [
                'survey_supervisor_reviewer_hub_id' => $hub->getKey(),
                'survey_supervisor_reviewer_id' => $reviewer->getKey(),
                'survey_id' => $reviewer->round->survey_id,
            ], $request);
        }

        return view('supervisor-review.show', $this->viewData($reviewer->fresh(), $snapshots, [
            'formAction' => route('supervisor-review.hub.instrument.store', compact('token', 'reviewer')),
            'hubUrl' => route('supervisor-review.hub.show', compact('token')),
        ]));
    }

    public function storeInstrument(
        string $token,
        SurveySupervisorReviewer $reviewer,
        Request $request,
        SupervisorReviewerHubTokenResolver $resolver,
        SupervisorReviewSnapshotService $snapshots,
        SubmitSupervisorInstrumentReviewAction $submitReview,
    ): RedirectResponse|Response|View {
        $hub = $resolver->resolve($token, $request, false);
        $this->authorizeAssignment($hub, $reviewer);

        if ($reviewer->isSubmitted()) {
            return view('supervisor-review.thank-you', [
                'reviewer' => $reviewer,
                'hubUrl' => route('supervisor-review.hub.show', compact('token')),
            ]);
        }
        if (! $reviewer->isReviewOpen()) {
            return response()->view('supervisor-review.unavailable', ['reviewer' => $reviewer], 403);
        }

        $data = $this->validatedReview($request, $reviewer);
        $snapshot = $this->snapshot($reviewer, $snapshots);
        $this->validateV2ItemCoverage($data['comments'] ?? [], $snapshot);

        $submitReview->handle($reviewer, [
            'final_decision' => $data['final_decision'],
            'final_notes' => $data['final_notes'] ?? null,
            'comments' => $this->normalizeComments($data['comments'] ?? []),
        ], $request, true);

        return redirect()->route('supervisor-review.hub.instrument.show', compact('token', 'reviewer'));
    }

    private function authorizeAssignment(mixed $hub, SurveySupervisorReviewer $reviewer): void
    {
        if (! $hub) {
            abort(404);
        }
        if (! $hub->isAccessible()) {
            abort(403);
        }
        abort_unless($reviewer->reviewer_hub_id === $hub->getKey(), 404);
        $reviewer->loadMissing('round.survey.project', 'comments');
    }

    private function viewData(SurveySupervisorReviewer $reviewer, SupervisorReviewSnapshotService $snapshots, array $navigation): array
    {
        $snapshot = $this->snapshot($reviewer, $snapshots);

        return [
            'reviewer' => $reviewer,
            'round' => $reviewer->round,
            'survey' => $reviewer->round->survey,
            'project' => $reviewer->round->survey->project,
            'snapshot' => $snapshot,
            'token' => null,
            'formAction' => $navigation['formAction'],
            'hubUrl' => $navigation['hubUrl'],
            'decisions' => SurveySupervisorReviewer::V2_DECISION_LABELS,
            'itemDecisions' => SurveySupervisorReviewComment::V2_DECISION_LABELS,
            'severities' => SurveySupervisorReviewComment::SEVERITIES,
        ];
    }

    private function validatedReview(Request $request, SurveySupervisorReviewer $reviewer): array
    {
        return $request->validate([
            'final_decision' => ['required', 'string', Rule::in(SurveySupervisorReviewer::V2_DECISIONS)],
            'final_notes' => ['nullable', 'string', 'max:10000'],
            'comments' => ['nullable', 'array'],
            'comments.*.comment_type' => ['required_with:comments', 'string', Rule::in(SurveySupervisorReviewComment::TYPES)],
            'comments.*.survey_question_id' => ['nullable', 'uuid'],
            'comments.*.target_key' => ['nullable', 'string', 'max:255'],
            'comments.*.target_label' => ['nullable', 'string', 'max:500'],
            'comments.*.comment' => ['nullable', 'string', 'max:10000'],
            'comments.*.suggested_revision' => ['nullable', 'string', 'max:10000'],
            'comments.*.severity' => ['nullable', 'string', Rule::in(SurveySupervisorReviewComment::SEVERITIES)],
            'comments.*.decision' => ['nullable', 'string', Rule::in(SurveySupervisorReviewComment::V2_DECISIONS)],
        ]);
    }

    private function snapshot(SurveySupervisorReviewer $reviewer, SupervisorReviewSnapshotService $snapshots): array
    {
        $snapshot = $reviewer->round->snapshot_json ?? [];

        return isset($snapshot['survey'], $snapshot['pages']) ? $snapshot : $snapshots->snapshot($reviewer->round->survey);
    }

    private function validateV2ItemCoverage(array $comments, array $snapshot): void
    {
        $expected = collect($snapshot['pages'] ?? [])->flatMap(fn (array $page): array => $page['questions'] ?? [])->merge($snapshot['questions_without_page'] ?? [])->pluck('id')->filter()->sort()->values();
        $submitted = collect($comments)->filter(fn (mixed $row): bool => is_array($row) && ($row['comment_type'] ?? null) === SurveySupervisorReviewComment::TYPE_ITEM)->filter(fn (array $row): bool => filled($row['decision'] ?? null))->pluck('survey_question_id')->filter()->sort()->values();
        if ($expected->all() !== $submitted->all()) {
            throw ValidationException::withMessages(['comments' => 'Pilih keputusan untuk setiap item pada versi yang direview.']);
        }
    }

    private function normalizeComments(array $comments): array
    {
        return collect($comments)->filter(fn (mixed $comment): bool => is_array($comment) && (filled($comment['comment'] ?? null) || filled($comment['decision'] ?? null)))->map(function (array $comment): array {
            $type = $comment['comment_type'];

            return [
                'comment_type' => $type,
                'survey_question_id' => $comment['survey_question_id'] ?? null,
                'target_key' => $comment['target_key'] ?? null,
                'target_label' => $comment['target_label'] ?? null,
                'comment' => $comment['comment'] ?? null,
                'suggested_revision' => $comment['suggested_revision'] ?? null,
                'severity' => $type === SurveySupervisorReviewComment::TYPE_ITEM ? ($comment['severity'] ?? null) : null,
                'decision' => $type === SurveySupervisorReviewComment::TYPE_ITEM ? ($comment['decision'] ?? null) : null,
            ];
        })->values()->all();
    }
}
