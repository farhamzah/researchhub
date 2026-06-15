<?php

namespace App\Http\Controllers;

use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\Survey;
use App\Models\SurveyValidationAssignment;
use App\Models\SurveyValidationRecommendation;
use App\Models\SurveyValidationRevision;
use App\Models\SurveyValidationRound;
use App\Modules\Validation\Actions\CreateSurveyValidationAssignmentAction;
use App\Modules\Validation\Actions\CreateSurveyValidationRoundAction;
use App\Modules\Validation\Actions\GenerateSurveyValidationLinkAction;
use App\Modules\Validation\Actions\RevokeSurveyValidationLinkAction;
use App\Modules\Validation\Actions\UpdateSurveyValidationRoundAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSurveyValidationController extends Controller
{
    public function index(Survey $survey, Request $request): View
    {
        Gate::authorize('manageValidation', $survey);

        $survey->load([
            'project',
            'questions.scoring.indicator',
            'validationRounds.assignments.validator',
            'validationRounds.assignments.recommendation',
            'validationRounds.assignments.scores.question',
        ]);

        return view('surveys.admin.validation.index', [
            'survey' => $survey,
            'rounds' => $survey->validationRounds,
            'availableValidators' => $this->availableValidators($survey, $request),
            'roundMethods' => SurveyValidationRound::METHOD_LABELS,
            'roundStatuses' => SurveyValidationRound::STATUS_LABELS,
            'assignmentStatuses' => SurveyValidationAssignment::STATUS_LABELS,
            'roleLabels' => ExpertValidatorProject::ROLE_LABELS,
            'feasibilityDecisions' => SurveyValidationRecommendation::DECISION_LABELS,
        ]);
    }

    public function storeRound(
        Survey $survey,
        Request $request,
        CreateSurveyValidationRoundAction $createRound,
    ): RedirectResponse {
        $createRound->handle($request->user(), $survey, $this->roundData($request), $request);

        return redirect()
            ->route('admin.surveys.validation.index', ['survey' => $survey])
            ->with('status', 'survey-validation-round-created');
    }

    public function updateRound(
        Survey $survey,
        SurveyValidationRound $round,
        Request $request,
        UpdateSurveyValidationRoundAction $updateRound,
    ): RedirectResponse {
        $updateRound->handle($request->user(), $survey, $round, $this->roundData($request), $request);

        return redirect()
            ->route('admin.surveys.validation.index', ['survey' => $survey])
            ->with('status', 'survey-validation-round-updated');
    }

    public function storeAssignment(
        Survey $survey,
        SurveyValidationRound $round,
        Request $request,
        CreateSurveyValidationAssignmentAction $createAssignment,
    ): RedirectResponse {
        $createAssignment->handle($request->user(), $survey, $round, $this->assignmentData($request), $request);

        return redirect()
            ->route('admin.surveys.validation.index', ['survey' => $survey])
            ->with('status', 'survey-validation-assignment-created');
    }

    public function generateLink(
        Survey $survey,
        SurveyValidationAssignment $assignment,
        Request $request,
        GenerateSurveyValidationLinkAction $generateLink,
    ): RedirectResponse {
        $result = $generateLink->handle($request->user(), $survey, $assignment, $request);

        return redirect()
            ->route('admin.surveys.validation.index', ['survey' => $survey])
            ->with('generated_validation_url', $result->url)
            ->with('generated_validation_assignment_id', $assignment->getKey())
            ->with('status', 'survey-validation-link-generated');
    }

    public function revokeLink(
        Survey $survey,
        SurveyValidationAssignment $assignment,
        Request $request,
        RevokeSurveyValidationLinkAction $revokeLink,
    ): RedirectResponse {
        $revokeLink->handle($request->user(), $survey, $assignment, $request);

        return redirect()
            ->route('admin.surveys.validation.index', ['survey' => $survey])
            ->with('status', 'survey-validation-link-revoked');
    }

    public function updateRevision(
        Survey $survey,
        SurveyValidationRevision $revision,
        Request $request,
    ): RedirectResponse {
        abort_unless($revision->survey_id === $survey->getKey(), 404);
        Gate::authorize('manageValidation', $survey);

        $data = $request->validate([
            'revision_action' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'string', Rule::in(SurveyValidationRevision::STATUSES)],
            'researcher_note' => ['nullable', 'string', 'max:10000'],
        ]);

        $revision->update($data);

        return redirect()
            ->route('admin.surveys.validation.index', ['survey' => $survey])
            ->with('status', 'survey-validation-revision-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function roundData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'method' => ['required', 'string', Rule::in(SurveyValidationRound::METHODS)],
            'rating_scale_min' => ['required', 'integer', 'min:1', 'max:100', 'lt:rating_scale_max'],
            'rating_scale_max' => ['required', 'integer', 'min:2', 'max:100'],
            'status' => ['required', 'string', Rule::in(SurveyValidationRound::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentData(Request $request): array
    {
        return $request->validate([
            'expert_validator_id' => ['required', 'string', Rule::exists('expert_validators', 'id')],
            'role' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function availableValidators(Survey $survey, Request $request): array
    {
        $projectAssignedIds = $survey->project
            ? $survey->project->expertValidatorAssignments()->pluck('expert_validator_id')->all()
            : [];

        $query = ExpertValidator::query()
            ->visibleTo($request->user())
            ->where('is_active', true);

        if ($projectAssignedIds !== []) {
            $query->orderByRaw('case when id in ('.implode(',', array_fill(0, count($projectAssignedIds), '?')).') then 0 else 1 end', $projectAssignedIds);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ExpertValidator $validator): array => [
                $validator->getKey() => $this->validatorLabel($validator, in_array($validator->getKey(), $projectAssignedIds, true)),
            ])
            ->all();
    }

    private function validatorLabel(ExpertValidator $validator, bool $assignedToProject): string
    {
        return collect([
            $validator->name,
            $validator->institution,
            $assignedToProject ? 'project assigned' : ($validator->is_global ? 'global' : 'private'),
        ])
            ->filter()
            ->join(' - ');
    }
}
