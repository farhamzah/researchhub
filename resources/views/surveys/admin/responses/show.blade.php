<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Response Detail - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">ResearchHub Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Response Detail</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey->title }}</p>
            </div>
            <a href="{{ route('admin.surveys.responses.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                Back to Responses
            </a>
        </div>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Respondent</p>
                <p class="mt-2 text-base font-semibold">{{ $privacyService->display($response->respondent, $survey, auth()->user()) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-2 text-base font-semibold">{{ $response->status }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submitted</p>
                <p class="mt-2 text-base font-semibold">{{ $response->submitted_at?->format('Y-m-d H:i') ?: 'Not submitted' }}</p>
            </div>
        </section>

        @php
            $identityFields = collect($exportRowWithIdentity)
                ->filter(fn ($value, $key) => str_starts_with((string) $key, 'identity_'));
        @endphp

        @if ($identityFields->isNotEmpty())
            <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-950">
                <h2 class="text-base font-semibold">Authorized Identity View</h2>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($identityFields as $key => $value)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ str_replace('identity_', '', $key) }}</dt>
                            <dd class="mt-1 text-sm">{{ $value ?: 'Not provided' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Answers</h2>
            <div class="mt-5 divide-y divide-gray-100">
                @forelse ($response->answers as $answer)
                    <div class="py-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $answer->question?->label ?: $answer->question_key }}</p>
                        <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700">{{ is_array($answer->answer_value) ? json_encode($answer->answer_value, JSON_UNESCAPED_SLASHES) : $answer->answer_value }}</p>
                    </div>
                @empty
                    <p class="py-6 text-sm text-gray-500">No answers stored for this response.</p>
                @endforelse
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Export Row Preview</h2>
            <p class="mt-1 text-sm text-gray-600">Default export row excludes identity columns unless permission allows and identity is requested.</p>
            <pre class="mt-4 rounded-md bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($exportRow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    </main>
</body>
</html>
