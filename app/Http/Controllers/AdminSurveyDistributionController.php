<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyDistributionBatch;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyValidationAssignment;
use App\Modules\Surveys\Actions\GenerateSurveyReadabilityLinkAction;
use App\Modules\Surveys\Actions\RevokeSurveyReadabilityLinkAction;
use App\Modules\Surveys\Actions\UpdateSurveyDistributionBatchAction;
use App\Modules\Surveys\Services\SurveyDistributionCenterService;
use App\Modules\Validation\Actions\GenerateSurveyValidationLinkAction;
use App\Modules\Validation\Actions\RevokeSurveyValidationLinkAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveyDistributionController extends Controller
{
    public function index(Survey $survey, Request $request, SurveyDistributionCenterService $distributionCenter): View
    {
        Gate::authorize('update', $survey);

        return view('surveys.admin.distribution.index', [
            'survey' => $survey,
            'distribution' => $distributionCenter->build($survey, $request->user()),
        ]);
    }

    public function report(Survey $survey, Request $request, SurveyDistributionCenterService $distributionCenter): View
    {
        Gate::authorize('update', $survey);

        return view('surveys.admin.distribution.report', [
            'survey' => $survey,
            'distribution' => $distributionCenter->build($survey, $request->user()),
            'generatedAt' => now(),
        ]);
    }

    public function updateBatch(
        Survey $survey,
        string $audience,
        Request $request,
        UpdateSurveyDistributionBatchAction $updateBatch,
    ): RedirectResponse {
        Gate::authorize('update', $survey);

        $data = $request->validate([
            'audience_type' => ['nullable', 'string', Rule::in(SurveyDistributionBatch::AUDIENCES)],
            'title' => ['nullable', 'string', 'max:255'],
            'message_subject' => ['nullable', 'string', 'max:255'],
            'message_body' => ['nullable', 'string', 'max:10000'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(SurveyDistributionBatch::STATUSES)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $updateBatch->handle($request->user(), $survey, $audience, $data, $request);

        return redirect()
            ->route('admin.surveys.distribution.index', ['survey' => $survey])
            ->with('status', 'survey-distribution-batch-updated');
    }

    public function generateValidationLink(
        Survey $survey,
        SurveyValidationAssignment $assignment,
        Request $request,
        GenerateSurveyValidationLinkAction $generateLink,
    ): RedirectResponse {
        $result = $generateLink->handle($request->user(), $survey, $assignment, $request);

        return redirect()
            ->route('admin.surveys.distribution.index', ['survey' => $survey])
            ->with('generated_validation_url', $result->url)
            ->with('generated_validation_assignment_id', $assignment->getKey())
            ->with('status', 'survey-validation-link-generated');
    }

    public function revokeValidationLink(
        Survey $survey,
        SurveyValidationAssignment $assignment,
        Request $request,
        RevokeSurveyValidationLinkAction $revokeLink,
    ): RedirectResponse {
        $revokeLink->handle($request->user(), $survey, $assignment, $request);

        return redirect()
            ->route('admin.surveys.distribution.index', ['survey' => $survey])
            ->with('status', 'survey-validation-link-revoked');
    }

    public function generateReadabilityLink(
        Survey $survey,
        SurveyReadabilityParticipant $participant,
        Request $request,
        GenerateSurveyReadabilityLinkAction $generateLink,
    ): RedirectResponse {
        $result = $generateLink->handle($request->user(), $survey, $participant, $request);

        return redirect()
            ->route('admin.surveys.distribution.index', ['survey' => $survey])
            ->with('generated_readability_url', $result->url)
            ->with('generated_readability_participant_id', $participant->getKey())
            ->with('status', 'survey-readability-link-generated');
    }

    public function revokeReadabilityLink(
        Survey $survey,
        SurveyReadabilityParticipant $participant,
        Request $request,
        RevokeSurveyReadabilityLinkAction $revokeLink,
    ): RedirectResponse {
        $revokeLink->handle($request->user(), $survey, $participant, $request);

        return redirect()
            ->route('admin.surveys.distribution.index', ['survey' => $survey])
            ->with('status', 'survey-readability-link-revoked');
    }
}
