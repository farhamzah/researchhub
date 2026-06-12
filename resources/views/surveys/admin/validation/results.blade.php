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
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Expert Validation Results</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey->title }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $survey->project?->title ?? 'No project' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Validation Rounds
                </a>
                <a href="{{ url('/admin/surveys') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Surveys
                </a>
            </div>
        </div>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
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
            <section class="mb-6 rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm">
                <h2 class="text-xl font-semibold">No submitted expert validation yet.</h2>
                <p class="mt-2 text-sm text-gray-600">Generate validation links and wait for validators to submit their assessment.</p>
            </section>
        @elseif ($result->summary['is_preliminary'])
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                Results are preliminary because not all validators have submitted.
            </section>
        @endif

        <section class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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

        <section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950">
            Thresholds are decision aids and should be confirmed by the researcher/supervisor. Default interpretation: valid if average Aiken's V and I-CVI are at least 0.80; revise for values around 0.60-0.79; reject when below 0.60.
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Validator Completion</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">Validator</th>
                            <th class="py-3 pr-4">Role</th>
                            <th class="py-3 pr-4">Status</th>
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
                                <td class="py-3 pr-4">{{ $validator['opened_at']?->format('Y-m-d H:i') ?? 'Not opened' }}</td>
                                <td class="py-3 pr-4">{{ $validator['submitted_at']?->format('Y-m-d H:i') ?? 'Not submitted' }}</td>
                                <td class="py-3 pr-4">
                                    <p>Expires: {{ $validator['expires_at']?->format('Y-m-d H:i') ?? 'No expiry' }}</p>
                                    <p>Revoked: {{ $validator['revoked_at']?->format('Y-m-d H:i') ?? 'No' }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-gray-500">No validators assigned.</td>
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
                            <th class="py-3 pr-4">Aiken V Relevance</th>
                            <th class="py-3 pr-4">Aiken V Clarity</th>
                            <th class="py-3 pr-4">Aiken V Language</th>
                            <th class="py-3 pr-4">Aiken V Appropriateness</th>
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
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['relevance_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['clarity_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['language_score']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMetric($item['aiken']['appropriateness_score']) }}</td>
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
                                <td colspan="11" class="py-6 text-center text-sm text-gray-500">No survey questions available for validation.</td>
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

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Copy-Ready Narrative</h2>
            <p class="mt-1 text-sm text-gray-600">Use this draft as a starting point and verify interpretation with the researcher/supervisor.</p>
            <textarea readonly rows="6" class="mt-4 block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm leading-6 shadow-sm">{{ $result->narrative }}</textarea>
        </section>
    </main>
</body>
</html>
