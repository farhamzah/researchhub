<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyReadabilityParticipant;
use App\Models\SurveyReadabilityRevision;
use App\Models\SurveyReadabilityRound;
use App\Modules\Surveys\Actions\CreateSurveyReadabilityParticipantAction;
use App\Modules\Surveys\Actions\CreateSurveyReadabilityRoundAction;
use App\Modules\Surveys\Actions\GenerateSurveyReadabilityLinkAction;
use App\Modules\Surveys\Actions\RevokeSurveyReadabilityLinkAction;
use App\Modules\Surveys\Actions\UpdateSurveyReadabilityRoundAction;
use App\Modules\Surveys\Services\SurveyReadabilityResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveyReadabilityController extends Controller
{
    public function index(Survey $survey, Request $request, SurveyReadabilityResultService $resultService): View
    {
        Gate::authorize('manageValidation', $survey);

        $survey->load([
            'project',
            'questions',
            'readabilityRounds.participants.response.questionFeedback.question',
        ]);

        $activeRound = $survey->readabilityRounds->sortByDesc('created_at')->first();

        return view('surveys.admin.readability.index', [
            'survey' => $survey,
            'rounds' => $survey->readabilityRounds,
            'roundStatuses' => SurveyReadabilityRound::STATUS_LABELS,
            'participantTypes' => SurveyReadabilityParticipant::TYPE_LABELS,
            'participantStatuses' => SurveyReadabilityParticipant::STATUS_LABELS,
            'revisionStatuses' => SurveyReadabilityRevision::STATUS_LABELS,
            'result' => $activeRound ? $resultService->analyze($activeRound) : null,
        ]);
    }

    public function storeRound(
        Survey $survey,
        Request $request,
        CreateSurveyReadabilityRoundAction $createRound,
    ): RedirectResponse {
        $createRound->handle($request->user(), $survey, $this->roundData($request), $request);

        return redirect()
            ->route('admin.surveys.readability.index', ['survey' => $survey])
            ->with('status', 'survey-readability-round-created');
    }

    public function updateRound(
        Survey $survey,
        SurveyReadabilityRound $round,
        Request $request,
        UpdateSurveyReadabilityRoundAction $updateRound,
    ): RedirectResponse {
        $updateRound->handle($request->user(), $survey, $round, $this->roundData($request), $request);

        return redirect()
            ->route('admin.surveys.readability.index', ['survey' => $survey])
            ->with('status', 'survey-readability-round-updated');
    }

    public function storeParticipant(
        Survey $survey,
        SurveyReadabilityRound $round,
        Request $request,
        CreateSurveyReadabilityParticipantAction $createParticipant,
    ): RedirectResponse {
        $createParticipant->handle($request->user(), $survey, $round, $this->participantData($request), $request);

        return redirect()
            ->route('admin.surveys.readability.index', ['survey' => $survey])
            ->with('status', 'survey-readability-participant-created');
    }

    public function generateLink(
        Survey $survey,
        SurveyReadabilityParticipant $participant,
        Request $request,
        GenerateSurveyReadabilityLinkAction $generateLink,
    ): RedirectResponse {
        $result = $generateLink->handle($request->user(), $survey, $participant, $request);

        return redirect()
            ->route('admin.surveys.readability.index', ['survey' => $survey])
            ->with('generated_readability_url', $result->url)
            ->with('generated_readability_participant_id', $participant->getKey())
            ->with('status', 'survey-readability-link-generated');
    }

    public function revokeLink(
        Survey $survey,
        SurveyReadabilityParticipant $participant,
        Request $request,
        RevokeSurveyReadabilityLinkAction $revokeLink,
    ): RedirectResponse {
        $revokeLink->handle($request->user(), $survey, $participant, $request);

        return redirect()
            ->route('admin.surveys.readability.index', ['survey' => $survey])
            ->with('status', 'survey-readability-link-revoked');
    }

    public function updateRevision(Survey $survey, SurveyReadabilityRevision $revision, Request $request): RedirectResponse
    {
        abort_unless($revision->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageValidation', $survey);

        $data = $request->validate([
            'revision_action' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'string', Rule::in(SurveyReadabilityRevision::STATUSES)],
            'researcher_note' => ['nullable', 'string', 'max:10000'],
        ]);

        $revision->update($data);

        return redirect()
            ->route('admin.surveys.readability.index', ['survey' => $survey])
            ->with('status', 'survey-readability-revision-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function roundData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(SurveyReadabilityRound::STATUSES)],
            'target_participants' => ['nullable', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function participantData(Request $request): array
    {
        return $request->validate([
            'participant_name' => ['nullable', 'string', 'max:255'],
            'participant_email' => ['nullable', 'email', 'max:255'],
            'participant_type' => ['nullable', 'string', Rule::in(SurveyReadabilityParticipant::TYPES)],
            'institution' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
