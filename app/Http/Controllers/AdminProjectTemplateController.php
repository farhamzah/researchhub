<?php

namespace App\Http\Controllers;

use App\Models\ResearchProject;
use App\Modules\Projects\Actions\CreateProjectFromTemplateAction;
use App\Modules\Projects\Services\ProjectTemplateCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminProjectTemplateController extends Controller
{
    public function index(ProjectTemplateCatalogService $catalog): View
    {
        Gate::authorize('create', ResearchProject::class);

        return view('projects.admin.templates.index', [
            'templates' => $catalog->all(),
        ]);
    }

    public function show(string $template, ProjectTemplateCatalogService $catalog): View
    {
        Gate::authorize('create', ResearchProject::class);

        return view('projects.admin.templates.show', [
            'template' => $catalog->find($template),
        ]);
    }

    public function store(string $template, Request $request, CreateProjectFromTemplateAction $createProject): RedirectResponse
    {
        Gate::authorize('create', ResearchProject::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'started_at' => ['nullable', 'date'],
            'target_finished_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'include_documents' => ['boolean'],
            'include_survey' => ['boolean'],
            'include_research_links' => ['boolean'],
        ]);

        $validated['include_documents'] = $request->boolean('include_documents');
        $validated['include_survey'] = $request->boolean('include_survey');
        $validated['include_research_links'] = $request->boolean('include_research_links');

        $project = $createProject->handle($request->user(), $template, $validated);

        return redirect()
            ->route('admin.projects.journey.show', ['researchProject' => $project])
            ->with('status', 'Project berhasil dibuat dari template. Ikuti alur riset untuk melanjutkan.');
    }
}
