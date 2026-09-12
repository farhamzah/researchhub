<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveySupervisorReviewRound;
use App\Modules\SupervisorReviews\Services\SupervisorReviewDocxExporter;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdminSurveySupervisorReviewDocxController extends Controller
{
    public function __invoke(Survey $survey, SurveySupervisorReviewRound $round, SupervisorReviewDocxExporter $exporter): Response
    {
        abort_unless($round->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageSupervisorReview', $survey);
        abort_unless($round->finalized_at !== null, 409, 'Laporan hanya tersedia setelah review final.');
        $round->setRelation('survey', $survey->load('project'));

        return response($exporter->export($round), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$exporter->filename($round).'"',
        ]);
    }
}
