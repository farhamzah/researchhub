@php
    $format = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 2);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Readability Results - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="mb-8 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                    <h1 class="mt-2 text-3xl font-semibold">Readability Test Results</h1>
                    <p class="mt-2 text-sm text-slate-600">{{ $survey->title }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $survey->project?->title ?? 'No project' }} | {{ $round->title }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.readability.report', ['survey' => $survey, 'round' => $round]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-50">Printable Report</a>
                    <a href="{{ route('admin.surveys.readability.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Readability Rounds</a>
                    <a href="{{ url('/admin/surveys') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Back to Surveys</a>
                </div>
            </div>
        </section>

        @if ($result['summary']['submitted_count'] === 0)
            <x-myriset.empty-state
                class="mb-6 bg-white shadow-sm"
                title="No submitted readability feedback yet."
                description="Generate pilot links and wait for participants to submit feedback before reading readability results."
            />
        @elseif ($result['summary']['has_preliminary_results'])
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                Results are preliminary because not all pilot participants have submitted.
            </section>
        @endif

        <section class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average Readability</p>
                <p class="mt-2 text-2xl font-semibold">{{ $format($result['summary']['average_readability_score']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</p>
                <p class="mt-2 text-lg font-semibold">{{ $result['summary']['category'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result['summary']['submitted_count'] }} / {{ $result['summary']['participant_count'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confusing Items</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result['summary']['confusing_item_count'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Instruction Clarity</p>
                <p class="mt-2 text-2xl font-semibold">{{ $format($result['summary']['average_instruction_clarity_score']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Terms</p>
                <p class="mt-2 text-2xl font-semibold">{{ $format($result['summary']['average_terminology_clarity_score']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Answer Options</p>
                <p class="mt-2 text-2xl font-semibold">{{ $format($result['summary']['average_answer_option_clarity_score']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Minutes</p>
                <p class="mt-2 text-2xl font-semibold">{{ $format($result['summary']['average_estimated_completion_minutes']) }}</p>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Issue Type Summary</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($result['issue_type_counts'] as $issue => $count)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ \App\Models\SurveyReadabilityQuestionFeedback::ISSUE_LABELS[$issue] ?? $issue }}: {{ $count }}</span>
                @empty
                    <p class="text-sm text-slate-500">No item-level issues submitted yet.</p>
                @endforelse
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Most Frequently Flagged Questions</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="py-3 pr-4">Question</th>
                            <th class="py-3 pr-4">Flags</th>
                            <th class="py-3 pr-4">Issues</th>
                            <th class="py-3 pr-4">Comments</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($result['flagged_questions'] as $question)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $question['question_text'] }}</td>
                                <td class="py-3 pr-4">{{ $question['count'] }}</td>
                                <td class="py-3 pr-4">
                                    @foreach ($question['issue_counts'] as $issue => $count)
                                        <span class="mr-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ \App\Models\SurveyReadabilityQuestionFeedback::ISSUE_LABELS[$issue] ?? $issue }}: {{ $count }}</span>
                                    @endforeach
                                </td>
                                <td class="py-3 pr-4">{{ implode(' | ', $question['comments']) ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-sm text-slate-500">No confusing questions submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Final Decisions</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($result['decision_counts'] as $decision => $count)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ \App\Models\SurveyReadabilityResponse::DECISION_LABELS[$decision] ?? $decision }}: {{ $count }}</span>
                @empty
                    <p class="text-sm text-slate-500">No final decisions submitted yet.</p>
                @endforelse
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Revision Matrix</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="py-3 pr-4">Question</th>
                            <th class="py-3 pr-4">Issue Summary</th>
                            <th class="py-3 pr-4">Researcher Action</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($result['revision_matrix'] as $revision)
                            <tr>
                                <td class="py-3 pr-4">{{ $revision['question_number'] ? '#'.$revision['question_number'].' ' : '' }}{{ $revision['question_text'] ?: 'Overall instrument' }}</td>
                                <td class="py-3 pr-4">{{ $revision['issue_summary'] }}</td>
                                <td class="py-3 pr-4">{{ $revision['revision_action'] ?: 'Pending researcher action' }}</td>
                                <td class="py-3 pr-4">{{ \App\Models\SurveyReadabilityRevision::STATUS_LABELS[$revision['status']] ?? $revision['status'] }}</td>
                                <td class="py-3 pr-4">{{ $revision['researcher_note'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-sm text-slate-500">No revision suggestions submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <x-academic-output-block
            title="Copy-Ready Readability Narrative"
            description="Use this as dissertation documentation text and verify wording with the researcher/supervisor."
            :narrative="$result['narrative']"
            source="Sumber: Readability Results"
        />
    </main>
</body>
</html>
