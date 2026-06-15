@php
    $formatMetric = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 3);
    $statusLabel = fn (string $status): string => match ($status) {
        \App\Modules\Validation\Services\SurveyValidationResultService::STATUS_VALID => 'Valid',
        \App\Modules\Validation\Services\SurveyValidationResultService::STATUS_REVISE => 'Revise',
        \App\Modules\Validation\Services\SurveyValidationResultService::STATUS_REJECT => 'Reject',
        default => 'No Data',
    };
    $statusClass = fn (string $status): string => match ($status) {
        \App\Modules\Validation\Services\SurveyValidationResultService::STATUS_VALID => 'bg-emerald-50 text-emerald-800',
        \App\Modules\Validation\Services\SurveyValidationResultService::STATUS_REVISE => 'bg-amber-50 text-amber-800',
        \App\Modules\Validation\Services\SurveyValidationResultService::STATUS_REJECT => 'bg-red-50 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validation Results - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div data-ui="myriset-page-header" class="mb-8 flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Expert Validation Results</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey->title }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $survey->project?->title ?? 'No project' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.surveys.validation.report', ['survey' => $survey, 'round' => $round]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-50">
                    Printable Report
                </a>
                <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Validation Rounds
                </a>
                <a href="{{ url('/admin/surveys') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Surveys
                </a>
            </div>
        </div>

        <section data-ui="myriset-section-card" class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Validation Round</p>
                    <p class="mt-2 font-semibold">{{ $round->title }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Method</p>
                    <p class="mt-2 font-semibold">{{ \App\Models\SurveyValidationRound::METHOD_LABELS[$round->method] ?? $round->method }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rating Scale</p>
                    <p class="mt-2 font-semibold">{{ $round->rating_scale_min }} to {{ $round->rating_scale_max }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Round Status</p>
                    <p class="mt-2 font-semibold">{{ \App\Models\SurveyValidationRound::STATUS_LABELS[$round->status] ?? $round->status }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Created By</p>
                    <p class="mt-2 font-semibold">{{ $round->creator?->name ?? 'Unknown' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submission Progress</p>
                    <p class="mt-2 font-semibold">{{ $result->summary['submitted_count'] }} / {{ $result->summary['assigned_count'] }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">CVR</p>
                    <p class="mt-2 text-sm text-gray-600">{{ $result->cvrNote }}</p>
                </div>
            </div>
        </section>

        @if ($result->summary['submitted_count'] === 0)
            <x-myriset.empty-state
                class="mb-6 bg-white shadow-sm"
                title="No submitted expert validation yet."
                description="Generate validation links and wait for validators to submit their assessment before reading Aiken/CVI results."
            />
        @elseif ($result->summary['is_preliminary'])
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                Results are preliminary because not all validators have submitted.
            </section>
        @endif

        <section class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Average Score</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result->summary['overall_average_score'] === null ? 'N/A' : number_format((float) $result->summary['overall_average_score'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Feasibility Percentage</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result->summary['percentage_feasibility'] === null ? 'N/A' : number_format((float) $result->summary['percentage_feasibility'], 2).'%' }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm md:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Validation Category</p>
                <p class="mt-2 text-lg font-semibold">{{ $result->summary['validation_category'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submitted Validators</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result->summary['submitted_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Questions</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result->summary['question_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Average Aiken's V</p>
                <p class="mt-2 text-2xl font-semibold">{{ $formatMetric($result->summary['average_aiken_v']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">S-CVI/Ave</p>
                <p class="mt-2 text-2xl font-semibold">{{ $formatMetric($result->summary['s_cvi_ave']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Average I-CVI</p>
                <p class="mt-2 text-2xl font-semibold">{{ $formatMetric($result->summary['average_i_cvi']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">S-CVI/UA</p>
                <p class="mt-2 text-2xl font-semibold">{{ $formatMetric($result->summary['s_cvi_ua']) }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Items Valid</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ $result->summary['valid_count'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Revise / Reject</p>
                <p class="mt-2 text-2xl font-semibold text-amber-900">{{ $result->summary['revise_count'] }} / {{ $result->summary['reject_count'] }}</p>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Aspect Summary</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">Aspect</th>
                            <th class="py-3 pr-4">Average Score</th>
                            <th class="py-3 pr-4">Aiken's V</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($result->aspectSummary as $aspect)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $aspect['label'] }}</td>
                                <td class="py-3 pr-4">{{ $aspect['average_score'] === null ? 'N/A' : number_format((float) $aspect['average_score'], 2) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($aspect['aiken_v']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Feasibility Decisions</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($result->summary['decision_counts'] as $decision => $count)
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">{{ \App\Models\SurveyValidationRecommendation::DECISION_LABELS[$decision] ?? $decision }}: {{ $count }}</span>
                @empty
                    <p class="text-sm text-gray-500">No final decisions submitted yet.</p>
                @endforelse
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950">
            Thresholds are decision aids and should be confirmed by the researcher/supervisor. Default interpretation: valid if average Aiken's V and I-CVI are at least 0.80; revise for values around 0.60-0.79; reject when below 0.60.
        </section>

        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <x-academic-output-block
                title="Expert Validation Summary"
                description="Narasi akademik non-AI dari status putaran validasi ahli."
                :narrative="$academicNarratives['expertValidation']"
                source="Sumber: Validation Results"
            />
            <x-academic-output-block
                title="Aiken/CVI Interpretation"
                description="Interpretasi bantu untuk metrik Aiken's V, I-CVI, S-CVI/Ave, dan S-CVI/UA."
                :narrative="$academicNarratives['validityInterpretation']"
                source="Sumber: Validation Results"
            />
        </div>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Validator Completion</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">Validator</th>
                            <th class="py-3 pr-4">Role</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Average Score</th>
                            <th class="py-3 pr-4">Decision</th>
                            <th class="py-3 pr-4">Opened At</th>
                            <th class="py-3 pr-4">Submitted At</th>
                            <th class="py-3 pr-4">Expiry / Revoked</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($result->validators as $validator)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $validator['validator_name'] }}</td>
                                <td class="py-3 pr-4">{{ \App\Models\ExpertValidatorProject::ROLE_LABELS[$validator['role']] ?? ($validator['role'] ?: 'No role') }}</td>
                                <td class="py-3 pr-4">{{ \App\Models\SurveyValidationAssignment::STATUS_LABELS[$validator['status']] ?? $validator['status'] }}</td>
                                <td class="py-3 pr-4">{{ $validator['average_score'] === null ? 'N/A' : number_format((float) $validator['average_score'], 2) }}</td>
                                <td class="py-3 pr-4">{{ $validator['feasibility_decision'] ? (\App\Models\SurveyValidationRecommendation::DECISION_LABELS[$validator['feasibility_decision']] ?? $validator['feasibility_decision']) : 'Not submitted' }}</td>
                                <td class="py-3 pr-4">{{ $validator['opened_at']?->format('Y-m-d H:i') ?? 'Not opened' }}</td>
                                <td class="py-3 pr-4">{{ $validator['submitted_at']?->format('Y-m-d H:i') ?? 'Not submitted' }}</td>
                                <td class="py-3 pr-4">
                                    <p>Expires: {{ $validator['expires_at']?->format('Y-m-d H:i') ?? 'No expiry' }}</p>
                                    <p>Revoked: {{ $validator['revoked_at']?->format('Y-m-d H:i') ?? 'No' }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-sm text-gray-500">No validators assigned.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Item-Level Results</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">#</th>
                            <th class="py-3 pr-4">Question</th>
                            <th class="py-3 pr-4">Type</th>
                            <th class="py-3 pr-4">Aiken V Content</th>
                            <th class="py-3 pr-4">Aiken V Language</th>
                            <th class="py-3 pr-4">Aiken V Construct</th>
                            <th class="py-3 pr-4">Aiken V Measurability</th>
                            <th class="py-3 pr-4">Aiken V Feasibility</th>
                            <th class="py-3 pr-4">Aiken V Ethics</th>
                            <th class="py-3 pr-4">Average Aiken V</th>
                            <th class="py-3 pr-4">I-CVI</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Recommendations</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($result->items as $item)
                            <tr>
                                <td class="py-3 pr-4">{{ $item['order'] }}</td>
                                <td class="py-3 pr-4">
                                    <p class="font-medium">{{ $item['question_text'] }}</p>
                                    @if ($item['indicator'])
                                        <p class="text-xs text-gray-500">{{ $item['indicator'] }}</p>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">{{ str_replace('_', ' ', $item['question_type']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['content_relevance_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['language_clarity_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['construct_alignment_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['measurability_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['feasibility_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['ethical_suitability_score']) }}</td>
                                <td class="py-3 pr-4 font-semibold">{{ $formatMetric($item['average_aiken_v']) }}</td>
                                <td class="py-3 pr-4 font-semibold">{{ $formatMetric($item['i_cvi']) }}</td>
                                <td class="py-3 pr-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass($item['status']) }}">{{ $statusLabel($item['status']) }}</span>
                                </td>
                                <td class="py-3 pr-4">
                                    @forelse ($item['recommendations'] as $recommendation => $count)
                                        <span class="mr-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ \App\Models\SurveyValidationScore::RECOMMENDATION_LABELS[$recommendation] ?? $recommendation }}: {{ $count }}</span>
                                    @empty
                                        <span class="text-gray-500">None</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="py-6 text-center text-sm text-gray-500">No survey questions available for validation.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Comments and Recommendations</h2>
            <div class="mt-5 space-y-4">
                @foreach ($result->comments as $commentGroup)
                    <article class="rounded-md border border-gray-200 p-4">
                        <h3 class="font-semibold">{{ $commentGroup['order'] }}. {{ $commentGroup['question_text'] }}</h3>
                        <div class="mt-3 space-y-3">
                            @forelse ($commentGroup['comments'] as $comment)
                                <div class="rounded-md bg-gray-50 p-3 text-sm">
                                    <p class="font-semibold">{{ $comment['validator_name'] }} - {{ \App\Models\ExpertValidatorProject::ROLE_LABELS[$comment['role']] ?? ($comment['role'] ?: 'Validator') }}</p>
                                    <p class="mt-1 text-gray-700">{{ $comment['comment'] ?: 'No comment.' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-gray-500">Recommendation: {{ \App\Models\SurveyValidationScore::RECOMMENDATION_LABELS[$comment['recommendation']] ?? ($comment['recommendation'] ?: 'None') }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No comments for this item.</p>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Revision Matrix</h2>
            <p class="mt-2 text-sm text-gray-600">Use this matrix for dissertation documentation and instrument revision tracking.</p>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">Validator</th>
                            <th class="py-3 pr-4">Validator Comment</th>
                            <th class="py-3 pr-4">Researcher Action</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Researcher Note</th>
                            <th class="py-3 pr-4">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($result->revisionMatrix as $revision)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $revision['validator_name'] }}</td>
                                <td class="py-3 pr-4">{{ $revision['validator_comment'] }}</td>
                                <td colspan="4" class="py-3 pr-4">
                                    <form method="POST" action="{{ route('admin.surveys.validation.revisions.update', ['survey' => $survey, 'revision' => $revision['id']]) }}" class="grid gap-3 lg:grid-cols-[1.2fr_0.8fr_1.2fr_auto]">
                                        @csrf
                                        @method('PUT')
                                        <textarea name="revision_action" rows="2" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" placeholder="Researcher action">{{ $revision['revision_action'] }}</textarea>
                                        <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                            @foreach (\App\Models\SurveyValidationRevision::STATUS_LABELS as $value => $label)
                                                <option value="{{ $value }}" @selected($revision['status'] === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="researcher_note" rows="2" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm" placeholder="Researcher note">{{ $revision['researcher_note'] }}</textarea>
                                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-gray-800">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-gray-500">No revision suggestions submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <x-academic-output-block
            title="Copy-Ready Narrative"
            description="Use this draft as a starting point and verify interpretation with the researcher/supervisor."
            :narrative="$result->narrative"
            source="Sumber: Validation Results"
        />
    </main>
</body>
</html>
