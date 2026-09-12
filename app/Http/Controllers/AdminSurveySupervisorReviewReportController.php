<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveySupervisorReviewRound;
use App\Modules\SupervisorReviews\Services\SupervisorReviewReportService;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminSurveySupervisorReviewReportController extends Controller
{
    public function __invoke(
        Survey $survey,
        SurveySupervisorReviewRound $round,
        SupervisorReviewSnapshotService $snapshots,
        SupervisorReviewReportService $reports,
    ): View {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageSupervisorReview', $survey);
        $round->setRelation('survey', $survey->load('project'));

        return view('surveys.admin.supervisor-review.printable-report', [
            'survey' => $survey,
            'round' => $round,
            'report' => $reports->build($round),
            'instrumentChanged' => $snapshots->instrumentChanged($round),
            'generatedAt' => now(),
        ]);
    }
}
