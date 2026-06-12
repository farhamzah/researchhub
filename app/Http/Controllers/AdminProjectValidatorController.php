<?php

namespace App\Http\Controllers;

use App\Models\ExpertValidator;
use App\Models\ExpertValidatorProject;
use App\Models\ResearchProject;
use App\Modules\Validation\Actions\CreateProjectValidatorAssignmentAction;
use App\Modules\Validation\Actions\DeleteProjectValidatorAssignmentAction;
use App\Modules\Validation\Actions\UpdateProjectValidatorAssignmentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProjectValidatorController extends Controller
{
    public function index(ResearchProject $researchProject, Request $request): View
    {
        Gate::authorize('update', $researchProject);

        $researchProject->load([
            'owner',
            'expertValidatorAssignments.validator',
            'expertValidatorAssignments.creator',
        ]);

        return view('projects.admin.validators.index', [
            'project' => $researchProject,
            'assignments' => $researchProject->expertValidatorAssignments,
            'availableValidators' => $this->availableValidators($request),
            'roleLabels' => ExpertValidatorProject::ROLE_LABELS,
            'statusLabels' => ExpertValidatorProject::STATUS_LABELS,
        ]);
    }

    public function store(
        ResearchProject $researchProject,
        Request $request,
        CreateProjectValidatorAssignmentAction $createAssignment,
    ): RedirectResponse {
        $createAssignment->handle($request->user(), $researchProject, $this->assignmentData($request), $request);

        return redirect()
            ->route('admin.projects.validators.index', ['researchProject' => $researchProject])
            ->with('status', 'expert-validator-assigned');
    }

    public function update(
        ResearchProject $researchProject,
        ExpertValidatorProject $assignment,
        Request $request,
        UpdateProjectValidatorAssignmentAction $updateAssignment,
    ): RedirectResponse {
        $updateAssignment->handle($request->user(), $researchProject, $assignment, $this->assignmentData($request, false), $request);

        return redirect()
            ->route('admin.projects.validators.index', ['researchProject' => $researchProject])
            ->with('status', 'expert-validator-assignment-updated');
    }

    public function destroy(
        ResearchProject $researchProject,
        ExpertValidatorProject $assignment,
        Request $request,
        DeleteProjectValidatorAssignmentAction $deleteAssignment,
    ): RedirectResponse {
        $deleteAssignment->handle($request->user(), $researchProject, $assignment, $request);

        return redirect()
            ->route('admin.projects.validators.index', ['researchProject' => $researchProject])
            ->with('status', 'expert-validator-detached');
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentData(Request $request, bool $includeValidator = true): array
    {
        $rules = [
            'role' => ['required', 'string', Rule::in(ExpertValidatorProject::ROLES)],
            'expertise_scope' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(ExpertValidatorProject::STATUSES)],
            'invited_at' => ['nullable', 'date'],
            'accepted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($includeValidator) {
            $rules['expert_validator_id'] = ['required', 'string', Rule::exists('expert_validators', 'id')];
        }

        return $request->validate($rules);
    }

    /**
     * @return array<string, string>
     */
    private function availableValidators(Request $request): array
    {
        return ExpertValidator::query()
            ->visibleTo($request->user())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ExpertValidator $validator): array => [
                $validator->getKey() => $this->validatorLabel($validator),
            ])
            ->all();
    }

    private function validatorLabel(ExpertValidator $validator): string
    {
        return collect([
            $validator->name,
            $validator->institution,
            $validator->is_global ? 'global' : 'private',
        ])
            ->filter()
            ->join(' - ');
    }
}
