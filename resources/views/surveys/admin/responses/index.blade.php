<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Responses - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Survey Responses</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey->title }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                {{-- UX-S10-02: Default CSV export is primary, safe, and identity-free --}}
                <a href="{{ route('admin.surveys.responses.export', ['survey' => $survey]) }}" class="rounded-md border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                    Export CSV Table Data
                </a>
                @if ($survey->canReceiveResponses())
                    <a href="{{ route('survey.show', ['survey' => $survey->slug]) }}" target="_blank" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        Public Link
                    </a>
                @endif
                <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Surveys
                </a>
            </div>
        </div>

        {{-- UX-S10-02: Identity export is separated into its own danger zone below main actions --}}
        @if ($privacyService->canViewFullIdentity(auth()->user(), $survey))
            <section class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-red-900">Identity Export</p>
                        <p class="mt-0.5 text-xs text-red-700">Contains decrypted respondent identity (name, email, identifier). Use only when authorized and ensure proper data handling.</p>
                    </div>
                    <a
                        href="{{ route('admin.surveys.responses.export', ['survey' => $survey, 'with_identity' => 1]) }}"
                        class="flex-shrink-0 rounded-md border border-red-400 bg-white px-4 py-2 text-sm font-semibold text-red-800 shadow-sm hover:bg-red-100"
                        onclick="return confirm('Export with respondent identity?\n\nThis file will contain decrypted personal data (name, email, identifier).\n\nEnsure you are authorized to access this data and handle it according to your research ethics protocol.')"
                    >
                        Export CSV with Respondent Identity
                    </a>
                </div>
            </section>
        @endif

        <section class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-2 text-lg font-semibold">{{ str_replace('_', ' ', $survey->status) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Identity Mode</p>
                <p class="mt-2 text-lg font-semibold">{{ str_replace('_', ' ', $survey->identity_mode) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Responses</p>
                <p class="mt-2 text-lg font-semibold">{{ $responses->total() }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Response List</h2>
                    <p class="mt-1 text-sm text-gray-600">Respondent identity is privacy-filtered by survey mode and permission.</p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">Respondent</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Submitted</th>
                            <th class="py-3 pr-4">Answers</th>
                            <th class="py-3 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($responses as $response)
                            <tr>
                                <td class="py-4 pr-4 font-medium">
                                    {{ $privacyService->display($response->respondent, $survey, auth()->user()) }}
                                </td>
                                {{-- UX-S10-07: Response status badge --}}
                                <td class="py-4 pr-4">
                                    @if ($response->status === \App\Models\SurveyResponse::STATUS_SUBMITTED)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Submitted</span>
                                    @elseif ($response->status === \App\Models\SurveyResponse::STATUS_STARTED)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Started</span>
                                    @elseif ($response->status === \App\Models\SurveyResponse::STATUS_VOID)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">Void</span>
                                    @else
                                        <span class="text-xs text-gray-500">{{ $response->status }}</span>
                                    @endif
                                </td>
                                <td class="py-4 pr-4">{{ $response->submitted_at?->format('Y-m-d H:i') ?: 'Not submitted' }}</td>
                                <td class="py-4 pr-4">{{ $response->answers->count() }}</td>
                                <td class="py-4 pr-4">
                                    <a href="{{ route('admin.surveys.responses.show', ['survey' => $survey, 'response' => $response]) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-600">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-gray-500">No responses yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $responses->links() }}
            </div>
        </section>

        {{-- UX-S10-07: Relabeled for clarity and reduced alarm for non-technical users --}}
        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">CSV Export Column Preview</h2>
            <p class="mt-1 text-sm text-gray-600">Shows the column structure of the default CSV export. Respondent identity columns are excluded unless you use the identity export above.</p>

            <div class="mt-4 overflow-x-auto">
                <pre class="rounded-md bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($exportPreviewRows->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </section>
    </main>
</body>
</html>
