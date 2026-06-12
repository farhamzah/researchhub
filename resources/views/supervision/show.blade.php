<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supervision Review - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Supervision Review</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $session->title }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ $project->title }}</p>
            <p class="mt-1 text-xs text-gray-500">Reviewer: {{ $reviewLink->recipientDisplayName() }}{{ $reviewLink->expires_at ? ' - Expires '.$reviewLink->expires_at->format('Y-m-d H:i') : '' }}</p>
        </section>

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Agenda</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $session->agenda ?: 'No agenda provided.' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Progress Report</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $session->progress_report ?: 'No progress report provided.' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Questions to Discuss</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $session->questions ?: 'No questions provided.' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Requested Feedback</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $session->requested_feedback ?: 'No specific feedback requested.' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Next Plan</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $session->next_plan ?: 'No next plan provided.' }}</p>
                </div>
            </div>
        </section>

        @if ($session->visibleResources->isNotEmpty())
            <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Shared Resources</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($session->visibleResources as $resource)
                        <article class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold">{{ $resource->displayTitle() }}</h3>
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800">{{ $resource->typeLabel() }}</span>
                            </div>
                            @if ($resource->description)
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $resource->description }}</p>
                            @endif
                            @if ($resource->notes)
                                <p class="mt-2 whitespace-pre-line text-xs text-gray-500">{{ $resource->notes }}</p>
                            @endif
                            @if ($resource->safePublicUrl())
                                <a href="{{ $resource->safePublicUrl() }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                                    Open resource
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the feedback form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Submit Supervisor Feedback</h2>
            <form method="POST" action="{{ route('supervision.review.store', ['token' => $token]) }}" class="mt-5 grid gap-4">
                @csrf
                <div>
                    <label class="text-sm font-semibold" for="decision">Decision</label>
                    <select id="decision" name="decision" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        @foreach ($decisionLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('decision') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold" for="general_feedback">General Feedback</label>
                    <textarea id="general_feedback" name="general_feedback" required rows="5" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('general_feedback') }}</textarea>
                </div>
                <div>
                    <label class="text-sm font-semibold" for="revision_notes">Revision Notes</label>
                    <textarea id="revision_notes" name="revision_notes" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('revision_notes') }}</textarea>
                </div>
                <div>
                    <label class="text-sm font-semibold" for="recommended_next_steps">Recommended Next Steps</label>
                    <textarea id="recommended_next_steps" name="recommended_next_steps" rows="4" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('recommended_next_steps') }}</textarea>
                </div>
                <div>
                    <label class="text-sm font-semibold" for="supervisor_note">Supervisor Note</label>
                    <textarea id="supervisor_note" name="supervisor_note" rows="3" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('supervisor_note') }}</textarea>
                </div>
                <div class="flex justify-end">
                    <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Submit Feedback</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
