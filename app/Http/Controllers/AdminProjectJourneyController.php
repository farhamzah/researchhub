<?php

namespace App\Http\Controllers;

use App\Models\ResearchProject;
use App\Modules\AcademicOutputs\Services\AcademicNarrativeService;
use App\Modules\Projects\Services\ProjectResearchJourneyService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminProjectJourneyController extends Controller
{
    public function show(
        ResearchProject $researchProject,
        ProjectResearchJourneyService $journeyService,
        AcademicNarrativeService $academicNarratives,
    ): View {
        Gate::authorize('view', $researchProject);

        return view('projects.admin.journey.show', [
            'project' => $researchProject,
            'journey' => $journeyService->build($researchProject),
            'academicNarratives' => [
                'projectProgress' => $academicNarratives->projectProgressSummary($researchProject),
                'documentProgress' => $academicNarratives->documentProgressSummary($researchProject),
                'followUp' => $academicNarratives->followUpSummary($researchProject),
            ],
        ]);
    }
}
