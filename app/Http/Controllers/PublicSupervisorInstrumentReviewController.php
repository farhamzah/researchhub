<?php

namespace App\Http\Controllers;

use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Modules\SupervisorReviews\Actions\SubmitSupervisorInstrumentReviewAction;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use App\Modules\SupervisorReviews\Services\SupervisorReviewTokenResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicSupervisorInstrumentReviewController extends Controller
{
    public function show(string $token, Request $request, SupervisorReviewTokenResolver $resolver, SupervisorReviewSnapshotService $snapshots): View|Response
    {
        $reviewer = $resolver->resolve($token, $request, true);
        if (! $reviewer) {
            abort(404);
        }
        if ($reviewer->isSubmitted()) {
            return view('supervisor-review.thank-you', ['reviewer' => $reviewer]);
        }
        if (! $reviewer->isAccessible()) {
            return response()->view('supervisor-review.unavailable', ['reviewer' => $reviewer], 403);
        }

        return view('supervisor-review.show', $this->viewData($reviewer, $token, $snapshots));
    }

    public function store(string $token, Request $request, SupervisorReviewTokenResolver $resolver, SupervisorReviewSnapshotService $snapshots, SubmitSupervisorInstrumentReviewAction $submitReview): View|RedirectResponse|Response
    {
        $reviewer = $resolver->resolve($token, $request, false);
        if (! $reviewer) {
            abort(404);
        }
        if ($reviewer->isSubmitted()) {
            return view('supervisor-review.thank-you', ['reviewer' => $reviewer]);
        }
        if (! $reviewer->isAccessible()) {
            return response()->view('supervisor-review.unavailable', ['reviewer' => $reviewer], 403);
        }

        $v2 = filled($reviewer->round->survey->instrument_version);
        $data = $request->validate([
            'final_decision' => ['required', 'string', Rule::in($v2 ? SurveySupervisorReviewer::V2_DECISIONS : SurveySupervisorReviewer::DECISIONS)],
            'final_notes' => ['nullable', 'string', 'max:10000'],
            'comments' => ['nullable', 'array'],
            'comments.*.comment_type' => ['required_with:comments', 'string', Rule::in(SurveySupervisorReviewComment::TYPES)],
            'comments.*.survey_question_id' => ['nullable', 'uuid'],
            'comments.*.target_key' => ['nullable', 'string', 'max:255'],
            'comments.*.target_label' => ['nullable', 'string', 'max:500'],
            'comments.*.comment' => ['nullable', 'string', 'max:10000'],
            'comments.*.suggested_revision' => ['nullable', 'string', 'max:10000'],
            'comments.*.severity' => ['nullable', 'string', Rule::in(SurveySupervisorReviewComment::SEVERITIES)],
            'comments.*.decision' => ['nullable', 'string', Rule::in($v2 ? SurveySupervisorReviewComment::V2_DECISIONS : SurveySupervisorReviewComment::DECISIONS)],
        ]);

        $snapshot = $this->snapshot($reviewer, $snapshots);
        if ($v2) {
            $this->validateV2ItemCoverage($data['comments'] ?? [], $snapshot);
        }

        $submitReview->handle($reviewer, [
            'final_decision' => $data['final_decision'],
            'final_notes' => $data['final_notes'] ?? null,
            'comments' => $this->normalizeComments($data['comments'] ?? []),
        ], $request);

        return redirect()->route('supervisor-review.survey.show', ['token' => $token])->with('status', 'supervisor-review-submitted');
    }

    private function viewData(SurveySupervisorReviewer $reviewer, string $token, SupervisorReviewSnapshotService $snapshots): array
    {
        $snapshot = $this->snapshot($reviewer, $snapshots);

        return [
            'reviewer' => $reviewer, 'round' => $reviewer->round, 'survey' => $reviewer->round->survey,
            'project' => $reviewer->round->survey->project, 'snapshot' => $snapshot, 'token' => $token,
            'decisions' => filled($reviewer->round->survey->instrument_version) ? SurveySupervisorReviewer::V2_DECISION_LABELS : SurveySupervisorReviewer::DECISION_LABELS,
            'itemDecisions' => filled($reviewer->round->survey->instrument_version) ? SurveySupervisorReviewComment::V2_DECISION_LABELS : array_combine(SurveySupervisorReviewComment::DECISIONS, SurveySupervisorReviewComment::DECISIONS),
            'severities' => SurveySupervisorReviewComment::SEVERITIES,
        ];
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
                'comment_type' => $type, 'survey_question_id' => $comment['survey_question_id'] ?? null,
                'target_key' => $comment['target_key'] ?? null, 'target_label' => $comment['target_label'] ?? null,
                'comment' => $comment['comment'] ?? null, 'suggested_revision' => $comment['suggested_revision'] ?? null,
                'severity' => $type === SurveySupervisorReviewComment::TYPE_ITEM ? ($comment['severity'] ?? null) : null,
                'decision' => $type === SurveySupervisorReviewComment::TYPE_ITEM ? ($comment['decision'] ?? null) : null,
            ];
        })->values()->all();
    }
}
