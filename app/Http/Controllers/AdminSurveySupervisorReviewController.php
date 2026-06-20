<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewRevision;
use App\Models\SurveySupervisorReviewRound;
use App\Modules\SupervisorReviews\Actions\GenerateSupervisorReviewLinkAction;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveySupervisorReviewController extends Controller
{
    public function index(Survey $survey, SupervisorReviewSnapshotService $snapshots): View
    {
        Gate::authorize('manageSupervisorReview', $survey);

        $survey->load([
            'project',
            'supervisorReviewRounds.reviewers.comments',
            'supervisorReviewRounds.revisions.reviewer',
        ]);

        return view('surveys.admin.supervisor-review.index', [
            'survey' => $survey,
            'rounds' => $survey->supervisorReviewRounds,
            'roundStatuses' => SurveySupervisorReviewRound::STATUS_LABELS,
            'reviewerStatuses' => SurveySupervisorReviewer::STATUS_LABELS,
            'decisions' => SurveySupervisorReviewer::DECISION_LABELS,
            'revisionStatuses' => SurveySupervisorReviewRevision::STATUS_LABELS,
            'instrumentChanged' => fn (SurveySupervisorReviewRound $round): bool => $snapshots->instrumentChanged($round),
        ]);
    }

    public function storeRound(Survey $survey, Request $request, SupervisorReviewSnapshotService $snapshots): RedirectResponse
    {
        Gate::authorize('manageSupervisorReview', $survey);

        $data = $this->roundData($request);
        $round = SurveySupervisorReviewRound::create(array_merge($data, [
            'survey_id' => $survey->getKey(),
            'research_project_id' => $survey->project_id,
            'created_by' => $request->user()?->getKey(),
        ]));

        if ($round->status === SurveySupervisorReviewRound::STATUS_OPEN) {
            $snapshots->ensureSnapshot($round);
            $round->forceFill(['opened_at' => now()])->save();
        }

        return redirect()
            ->route('admin.surveys.supervisor-review.index', ['survey' => $survey])
            ->with('status', 'survey-supervisor-review-round-created');
    }

    public function updateRound(Survey $survey, SurveySupervisorReviewRound $round, Request $request, SupervisorReviewSnapshotService $snapshots): RedirectResponse
    {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageSupervisorReview', $survey);

        $data = $this->roundData($request);
        $round->update($data);

        if ($round->status === SurveySupervisorReviewRound::STATUS_OPEN) {
            $snapshots->ensureSnapshot($round);
            $round->forceFill(['opened_at' => $round->opened_at ?? now()])->save();
        }

        if ($round->status === SurveySupervisorReviewRound::STATUS_CLOSED && $round->closed_at === null) {
            $round->forceFill(['closed_at' => now()])->save();
        }

        return redirect()
            ->route('admin.surveys.supervisor-review.index', ['survey' => $survey])
            ->with('status', 'survey-supervisor-review-round-updated');
    }

    public function storeReviewer(Survey $survey, SurveySupervisorReviewRound $round, Request $request): RedirectResponse
    {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageSupervisorReview', $survey);

        $data = $request->validate([
            'supervisor_name' => ['required', 'string', 'max:255'],
            'supervisor_email' => ['nullable', 'email', 'max:255'],
            'supervisor_code' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $round->reviewers()->create(array_merge($data, [
            'status' => SurveySupervisorReviewer::STATUS_NOT_OPENED,
            'created_by' => $request->user()?->getKey(),
        ]));

        return redirect()
            ->route('admin.surveys.supervisor-review.index', ['survey' => $survey])
            ->with('status', 'survey-supervisor-reviewer-created');
    }

    public function generateLink(
        Survey $survey,
        SurveySupervisorReviewer $reviewer,
        Request $request,
        GenerateSupervisorReviewLinkAction $generateLink,
    ): RedirectResponse {
        $result = $generateLink->handle($request->user(), $survey, $reviewer, $request);

        return redirect()
            ->route('admin.surveys.supervisor-review.index', ['survey' => $survey])
            ->with('generated_supervisor_review_url', $result->url)
            ->with('generated_supervisor_reviewer_id', $reviewer->getKey())
            ->with('status', 'survey-supervisor-review-link-generated');
    }

    public function revokeLink(Survey $survey, SurveySupervisorReviewer $reviewer): RedirectResponse
    {
        $reviewer->loadMissing('round');
        abort_unless($reviewer->round?->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageSupervisorReview', $survey);

        $reviewer->markRevoked();

        return redirect()
            ->route('admin.surveys.supervisor-review.index', ['survey' => $survey])
            ->with('status', 'survey-supervisor-review-link-revoked');
    }

    public function updateRevision(Survey $survey, SurveySupervisorReviewRevision $revision, Request $request): RedirectResponse
    {
        abort_unless($revision->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageSupervisorReview', $survey);

        $data = $request->validate([
            'researcher_response' => ['nullable', 'string', 'max:10000'],
            'action_taken' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'string', Rule::in(SurveySupervisorReviewRevision::STATUSES)],
            'revised_version' => ['nullable', 'string', 'max:255'],
            'revised_at' => ['nullable', 'date'],
        ]);

        $revision->update($data);

        return redirect()
            ->route('admin.surveys.supervisor-review.index', ['survey' => $survey])
            ->with('status', 'survey-supervisor-review-revision-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function roundData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(SurveySupervisorReviewRound::STATUSES)],
            'due_date' => ['nullable', 'date'],
        ]);
    }
}
