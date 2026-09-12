<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewerHub;
use App\Modules\SupervisorReviews\Actions\CreateSupervisorReviewerHubAction;
use App\Modules\SupervisorReviews\Actions\GenerateSupervisorReviewerHubLinkAction;
use App\Modules\SupervisorReviews\Actions\RevokeSupervisorReviewerHubAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSupervisorReviewerHubController extends Controller
{
    private const REQUIRED_CODES = [
        'S01-STUDENT-NEEDS',
        'S02-LECTURER-NEEDS',
        'S03-PRACTITIONER-INTERVIEW',
    ];

    public function index(Survey $survey): View
    {
        Gate::authorize('manageSupervisorReview', $survey);
        $survey->loadMissing('project');

        return view('surveys.admin.supervisor-review.hubs', [
            'survey' => $survey,
            'project' => $survey->project,
            'reviewerGroups' => $this->reviewerGroups($survey),
            'hubs' => SurveySupervisorReviewerHub::query()
                ->where('research_project_id', $survey->project_id)
                ->with('reviewers.round.survey')
                ->orderBy('supervisor_name')
                ->get()
                ->keyBy('supervisor_code'),
        ]);
    }

    public function generate(
        Survey $survey,
        Request $request,
        CreateSupervisorReviewerHubAction $create,
        GenerateSupervisorReviewerHubLinkAction $generate,
    ): RedirectResponse {
        Gate::authorize('manageSupervisorReview', $survey);
        $data = $request->validate(['supervisor_code' => ['required', 'string', 'max:50']]);
        $reviewers = $this->reviewerGroups($survey)->get($data['supervisor_code']);

        if (! $reviewers instanceof Collection || $reviewers->count() !== 3) {
            throw ValidationException::withMessages([
                'supervisor_code' => 'Pembimbing harus memiliki assignment aktif untuk S01, S02, dan S03.',
            ]);
        }

        $hub = SurveySupervisorReviewerHub::query()
            ->where('research_project_id', $survey->project_id)
            ->where('supervisor_code', $data['supervisor_code'])
            ->first();

        $result = $hub
            ? $generate->handle($request->user(), $hub, now()->addDays(30))
            : $create->handle($request->user(), $survey->project, $reviewers, now()->addDays(30));

        return redirect()
            ->route('admin.surveys.supervisor-review.hubs.index', ['survey' => $survey])
            ->with('generated_supervisor_reviewer_hub_url', $result->url)
            ->with('generated_supervisor_reviewer_hub_code', $result->hub->supervisor_code)
            ->with('status', 'supervisor-reviewer-hub-link-generated');
    }

    public function revoke(
        Survey $survey,
        SurveySupervisorReviewerHub $hub,
        Request $request,
        RevokeSupervisorReviewerHubAction $revoke,
    ): RedirectResponse {
        abort_unless($hub->research_project_id === $survey->project_id, 404);
        Gate::authorize('manageSupervisorReview', $survey);
        $revoke->handle($request->user(), $hub);

        return redirect()
            ->route('admin.surveys.supervisor-review.hubs.index', ['survey' => $survey])
            ->with('status', 'supervisor-reviewer-hub-link-revoked');
    }

    /** @return Collection<string, Collection<int, SurveySupervisorReviewer>> */
    private function reviewerGroups(Survey $survey): Collection
    {
        return SurveySupervisorReviewer::query()
            ->whereNotNull('supervisor_code')
            ->whereHas('round.survey', fn ($query) => $query
                ->where('project_id', $survey->project_id)
                ->where('instrument_version', '2.0')
                ->whereIn('instrument_code', self::REQUIRED_CODES))
            ->with('round.survey')
            ->get()
            ->groupBy('supervisor_code')
            ->filter(function (Collection $reviewers): bool {
                $codes = $reviewers->pluck('round.survey.instrument_code')->sort()->values()->all();

                return $reviewers->count() === 3
                    && $codes === collect(self::REQUIRED_CODES)->sort()->values()->all();
            });
    }
}
