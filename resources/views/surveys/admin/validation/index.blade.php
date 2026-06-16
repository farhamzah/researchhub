<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Expert Validation - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Survey Expert Validation</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey->title }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $survey->project?->title ?? 'No project' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Builder
                </a>
                <a href="{{ route('admin.surveys.readability.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Readability Test
                </a>
                <a href="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Analysis Dashboard
                </a>
                <a href="{{ url('/admin/surveys') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Surveys
                </a>
            </div>
        </div>

        @if (session('generated_validation_url'))
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 shadow-sm">
                <p class="font-semibold">Copy this validation URL now. It is shown only once.</p>
                <p class="mt-1">The raw token is not stored and cannot be recovered later. Regenerate a link if you lose it before submission.</p>
                <input readonly value="{{ session('generated_validation_url') }}" class="mt-3 block w-full rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm">
            </section>
        @endif

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the validation form.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rounds</p>
                <p class="mt-2 text-2xl font-semibold">{{ $rounds->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Validators</p>
                <p class="mt-2 text-2xl font-semibold">{{ $rounds->sum(fn ($round) => $round->assignments->count()) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submitted</p>
                <p class="mt-2 text-2xl font-semibold">{{ $rounds->sum(fn ($round) => $round->assignments->where('status', \App\Models\SurveyValidationAssignment::STATUS_SUBMITTED)->count()) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Questions</p>
                <p class="mt-2 text-2xl font-semibold">{{ $survey->questions->count() }}</p>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Create Validation Round</h2>
            <form method="POST" action="{{ route('admin.surveys.validation.rounds.store', ['survey' => $survey]) }}" class="mt-5 grid gap-4 lg:grid-cols-3">
                @csrf
                <div>
                    <label for="round_title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input id="round_title" name="title" required value="{{ old('title', 'Expert Validation Round') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="round_method" class="block text-sm font-medium text-gray-700">Method</label>
                    <select id="round_method" name="method" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        @foreach ($roundMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="round_status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="round_status" name="status" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        @foreach ($roundStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($value === \App\Models\SurveyValidationRound::STATUS_OPEN)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rating_scale_min" class="block text-sm font-medium text-gray-700">Rating Min</label>
                    <input id="rating_scale_min" name="rating_scale_min" type="number" min="1" value="1" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="rating_scale_max" class="block text-sm font-medium text-gray-700">Rating Max</label>
                    <input id="rating_scale_max" name="rating_scale_max" type="number" min="2" value="5" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="starts_at" class="block text-sm font-medium text-gray-700">Starts At</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div>
                    <label for="ends_at" class="block text-sm font-medium text-gray-700">Ends At</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div class="lg:col-span-2">
                    <label for="round_description" class="block text-sm font-medium text-gray-700">Description</label>
                    <input id="round_description" name="description" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                </div>
                <div class="lg:col-span-3">
                    <label for="instructions" class="block text-sm font-medium text-gray-700">Instructions</label>
                    <textarea id="instructions" name="instructions" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">Mohon menilai setiap butir instrumen dengan skala 1-5 berdasarkan content relevance, language clarity, construct alignment, measurability, feasibility of use, dan ethical/privacy suitability.</textarea>
                </div>
                <button type="submit" class="lg:col-span-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                    Create Validation Round
                </button>
            </form>
        </section>

        <section class="space-y-6">
            @forelse ($rounds as $round)
                @php
                    $submittedCount = $round->assignments->where('status', \App\Models\SurveyValidationAssignment::STATUS_SUBMITTED)->count();
                    $pendingCount = $round->assignments->whereIn('status', [
                        \App\Models\SurveyValidationAssignment::STATUS_PENDING,
                        \App\Models\SurveyValidationAssignment::STATUS_LINK_GENERATED,
                        \App\Models\SurveyValidationAssignment::STATUS_OPENED,
                    ])->count();
                    $inactiveCount = $round->assignments->whereIn('status', [
                        \App\Models\SurveyValidationAssignment::STATUS_EXPIRED,
                        \App\Models\SurveyValidationAssignment::STATUS_REVOKED,
                    ])->count();
                @endphp

                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-semibold">{{ $round->title }}</h2>
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{{ $roundStatuses[$round->status] ?? $round->status }}</span>
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800">{{ $roundMethods[$round->method] ?? $round->method }}</span>
                            </div>
                            <p class="mt-2 text-sm text-gray-600">{{ $round->description ?: 'No description.' }}</p>
                            <p class="mt-1 text-xs text-gray-500">Scale {{ $round->rating_scale_min }} to {{ $round->rating_scale_max }}</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-sm">
                            <div class="rounded-md bg-emerald-50 px-3 py-2 text-emerald-900">
                                <p class="font-semibold">{{ $submittedCount }}</p>
                                <p class="text-xs">Submitted</p>
                            </div>
                            <div class="rounded-md bg-amber-50 px-3 py-2 text-amber-900">
                                <p class="font-semibold">{{ $pendingCount }}</p>
                                <p class="text-xs">Pending</p>
                            </div>
                            <div class="rounded-md bg-gray-100 px-3 py-2 text-gray-700">
                                <p class="font-semibold">{{ $inactiveCount }}</p>
                                <p class="text-xs">Inactive</p>
                            </div>
                        </div>
                    </div>

                    <details class="mt-5 rounded-md border border-gray-200 bg-gray-50">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700">Edit validation round</summary>
                        <form method="POST" action="{{ route('admin.surveys.validation.rounds.update', ['survey' => $survey, 'round' => $round]) }}" class="grid gap-3 border-t border-gray-200 p-4 lg:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <input name="title" value="{{ $round->title }}" required class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <select name="method" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                @foreach ($roundMethods as $value => $label)
                                    <option value="{{ $value }}" @selected($round->method === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                @foreach ($roundStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected($round->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="rating_scale_min" type="number" min="1" value="{{ $round->rating_scale_min }}" required class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <input name="rating_scale_max" type="number" min="2" value="{{ $round->rating_scale_max }}" required class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <input name="starts_at" type="datetime-local" value="{{ $round->starts_at?->format('Y-m-d\TH:i') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <input name="ends_at" type="datetime-local" value="{{ $round->ends_at?->format('Y-m-d\TH:i') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <input name="description" value="{{ $round->description }}" class="lg:col-span-2 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <textarea name="instructions" rows="3" class="lg:col-span-3 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $round->instructions }}</textarea>
                            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">Save Round</button>
                        </form>
                    </details>

                    <div class="mt-5 rounded-md border border-gray-200 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="font-semibold">Assign Validator</h3>
                            <a href="{{ route('admin.surveys.validation.results.show', ['survey' => $survey, 'round' => $round]) }}" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                                View Results
                            </a>
                            <a href="{{ route('admin.surveys.validation.report', ['survey' => $survey, 'round' => $round]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">
                                Printable Report
                            </a>
                        </div>
                        @if ($availableValidators === [])
                            <p class="mt-3 rounded-md border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600">No active validators are available for this survey. Add a validator to the project or registry first.</p>
                        @else
                            <form method="POST" action="{{ route('admin.surveys.validation.assignments.store', ['survey' => $survey, 'round' => $round]) }}" class="mt-4 grid gap-3 lg:grid-cols-4">
                                @csrf
                                <select name="expert_validator_id" required class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm lg:col-span-2">
                                    @foreach ($availableValidators as $validatorId => $label)
                                        <option value="{{ $validatorId }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select name="role" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                    <option value="">No role</option>
                                    @foreach ($roleLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="expires_at" type="datetime-local" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" aria-label="Assignment expiry">
                                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600 lg:col-span-4">
                                    Assign Validator
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="py-3 pr-4">Validator</th>
                                    <th class="py-3 pr-4">Role</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-4">Dates</th>
                                    <th class="py-3 pr-4">Scores</th>
                                    <th class="py-3 pr-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($round->assignments as $assignment)
                                    <tr>
                                        <td class="py-3 pr-4">
                                            <p class="font-medium">{{ $assignment->validator?->name ?? 'Missing validator' }}</p>
                                            <p class="text-xs text-gray-500">{{ $assignment->validator?->institution ?? 'No institution recorded' }}</p>
                                        </td>
                                        <td class="py-3 pr-4">{{ $roleLabels[$assignment->role] ?? ($assignment->role ?: 'No role') }}</td>
                                        <td class="py-3 pr-4">{{ $assignmentStatuses[$assignment->status] ?? $assignment->status }}</td>
                                        <td class="py-3 pr-4">
                                            <p>Opened: {{ $assignment->opened_at?->format('Y-m-d H:i') ?? 'Not yet' }}</p>
                                            <p>Submitted: {{ $assignment->submitted_at?->format('Y-m-d H:i') ?? 'Not yet' }}</p>
                                            <p>Expires: {{ $assignment->expires_at?->format('Y-m-d H:i') ?? 'No expiry' }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            @if ($assignment->scores->isEmpty())
                                                <span class="text-gray-500">No scores yet</span>
                                            @else
                                                <details>
                                                    <summary class="cursor-pointer font-semibold text-emerald-700">
                                                        {{ $assignment->scores->count() }} scored items
                                                        @if ($assignment->recommendation)
                                                            - Avg {{ number_format((float) $assignment->recommendation->overall_score, 2) }}
                                                            - {{ $feasibilityDecisions[$assignment->recommendation->feasibility_decision] ?? $assignment->recommendation->feasibility_decision }}
                                                        @endif
                                                    </summary>
                                                    <div class="mt-2 max-w-xl space-y-2">
                                                        @foreach ($assignment->scores as $score)
                                                            <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                                                                <p class="font-medium">{{ $score->question?->label }}</p>
                                                                <p class="mt-1 text-xs text-gray-600">Content {{ $score->content_relevance_score ?? $score->relevance_score }} | Language {{ $score->language_clarity_score ?? $score->clarity_score }} | Construct {{ $score->construct_alignment_score ?? $score->appropriateness_score }} | Measurable {{ $score->measurability_score ?? $score->clarity_score }} | Feasible {{ $score->feasibility_score ?? $score->appropriateness_score }} | Ethics {{ $score->ethical_suitability_score ?? $score->relevance_score }} - {{ \App\Models\SurveyValidationScore::RECOMMENDATION_LABELS[$score->recommendation] ?? $score->recommendation }}</p>
                                                                @if ($score->comment)
                                                                    <p class="mt-1 text-xs text-gray-600">{{ $score->comment }}</p>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4">
                                            <div class="flex flex-col gap-2">
                                                <form method="POST" action="{{ route('admin.surveys.validation.assignments.generate-link', ['survey' => $survey, 'assignment' => $assignment]) }}">
                                                    @csrf
                                                    <button type="submit" @disabled($assignment->isSubmitted()) class="w-full rounded-md bg-gray-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-gray-800 disabled:bg-gray-300">
                                                        {{ $assignment->token_hash ? 'Regenerate Link' : 'Generate Link' }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.surveys.validation.assignments.revoke-link', ['survey' => $survey, 'assignment' => $assignment]) }}">
                                                    @csrf
                                                    <button type="submit" onclick="return confirm('Revoke this validation link?')" @disabled($assignment->isSubmitted() || $assignment->isRevoked()) class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:border-gray-200 disabled:text-gray-400">
                                                        Revoke Link
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-sm text-gray-500">No validators assigned to this validation round.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <section class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm">
                    <h2 class="text-xl font-semibold">No validation rounds yet</h2>
                    <p class="mt-2 text-sm text-gray-600">Create a validation round to generate secure public scoring links for expert validators.</p>
                </section>
            @endforelse
        </section>
    </main>
</body>
</html>
