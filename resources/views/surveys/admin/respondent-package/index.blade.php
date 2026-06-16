@php
    $statusClass = fn (string $status): string => match ($status) {
        'Ready for Real Distribution', 'Pilot Passed', 'Ready', 'passed', 'submitted' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'Ready for Pilot', 'Pilot In Progress', 'active' => 'border-blue-200 bg-blue-50 text-blue-900',
        'failed', 'Not Ready', 'Not ready', 'revoked' => 'border-red-200 bg-red-50 text-red-900',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
    $checkLabels = [
        'intro_ok' => 'Intro page appears',
        'consent_ok' => 'Consent appears',
        'questions_ok' => 'Questions render',
        'required_validation_ok' => 'Required questions work',
        'submit_ok' => 'Submit works',
        'thank_you_ok' => 'Thank you page appears',
        'excluded_from_analysis_ok' => 'Response stored as test data and excluded',
        'mobile_view_ok' => 'Mobile view checked',
        'desktop_view_ok' => 'Desktop view checked',
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Final Respondent Link Package & Pilot Test - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-4xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Analysis</p>
                    <h1 class="mt-2 text-3xl font-semibold">Final Respondent Link Package & Pilot Test</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $survey->project?->title ?? 'No project' }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Final safety layer before PharmVR Analysis links are sent to real respondents. No email or WhatsApp message is sent from this page.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($package['nav'] as $label => $url)
                        <a href="{{ $url }}" class="{{ $label === 'Respondent Package' ? 'bg-emerald-700 text-white hover:bg-emerald-600' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }} rounded-md px-4 py-2 text-sm font-semibold shadow-sm">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </section>

        @if (session('status'))
            <section class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if (session('generated_pilot_url'))
            <section class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-5 text-amber-950 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide">Pilot Link Generated</p>
                <p class="mt-2 text-sm leading-6">Copy this pilot link now. Only the token hash is stored; regenerate if you need a new visible pilot URL.</p>
                <div class="mt-3 flex flex-col gap-2 lg:flex-row">
                    <input id="generated_pilot_url" readonly value="{{ session('generated_pilot_url') }}" class="min-w-0 flex-1 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm">
                    <button type="button" data-copy-target="generated_pilot_url" class="rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-600">Copy Pilot Link</button>
                    <a href="{{ session('generated_pilot_url') }}" target="_blank" class="rounded-md border border-amber-300 bg-white px-4 py-2 text-center text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-100">Open Pilot Link</a>
                </div>
            </section>
        @endif

        <section class="mt-6 rounded-lg border p-6 shadow-sm {{ $statusClass($package['launch']['recommendation']) }}">
            <p class="text-xs font-semibold uppercase tracking-wide">Overall Launch Readiness</p>
            <h2 class="mt-1 text-3xl font-semibold">{{ $package['launch']['recommendation'] }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div><p class="text-xs uppercase tracking-wide">Preflight QA</p><p class="font-semibold">{{ $package['launch']['preflight_status'] }}</p></div>
                <div><p class="text-xs uppercase tracking-wide">Last Preflight Review</p><p class="font-semibold">{{ $package['launch']['last_preflight_review_at']?->format('Y-m-d H:i') ?? 'No ready snapshot' }}</p></div>
                <div><p class="text-xs uppercase tracking-wide">Analysis Package</p><p class="font-semibold">{{ $package['launch']['analysis_package_status'] }}</p></div>
                <div><p class="text-xs uppercase tracking-wide">Distribution Center</p><p class="font-semibold">{{ $package['launch']['distribution_status'] }}</p></div>
                <div><p class="text-xs uppercase tracking-wide">Collection Targets</p><p class="font-semibold">{{ $package['launch']['collection_status'] }}</p></div>
                <div><p class="text-xs uppercase tracking-wide">Pilot Test</p><p class="font-semibold">{{ $package['launch']['pilot_status'] }}</p></div>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Final Real Links</p>
            <h2 class="mt-1 text-xl font-semibold">Links and copy-ready messages</h2>
            <div class="mt-5 grid gap-4 xl:grid-cols-3">
                @foreach ($package['real_links'] as $index => $row)
                    @php
                        $linkId = 'real_link_'.$index;
                        $waId = 'real_wa_'.$index;
                        $emailId = 'real_email_'.$index;
                    @endphp
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">Real Link</span>
                                <h3 class="mt-3 font-semibold">{{ $row['instrument'] }}</h3>
                                <p class="text-sm text-slate-600">{{ $row['audience'] }}</p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $statusClass($row['status']) }}">{{ $row['status'] }}</span>
                        </div>
                        <input id="{{ $linkId }}" readonly value="{{ $row['link'] ?? 'Link not available' }}" class="mt-4 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs">
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" data-copy-target="{{ $linkId }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold">Copy Real Link</button>
                            @if ($row['link'])
                                <a href="{{ $row['link'] }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold">Open Link</a>
                            @endif
                        </div>
                        <label class="mt-4 block text-xs font-semibold text-slate-600">WhatsApp message</label>
                        <textarea id="{{ $waId }}" readonly rows="5" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs">{{ $row['whatsapp_message'] }}</textarea>
                        <button type="button" data-copy-target="{{ $waId }}" class="mt-2 rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800">Copy Message</button>
                        <label class="mt-4 block text-xs font-semibold text-slate-600">Email message</label>
                        <textarea id="{{ $emailId }}" readonly rows="6" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs">{{ $row['email_message'] }}</textarea>
                        <button type="button" data-copy-target="{{ $emailId }}" class="mt-2 rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800">Copy Email</button>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pilot/Test Links</p>
            <h2 class="mt-1 text-xl font-semibold">Do not send pilot links to real respondents</h2>
            <p class="mt-2 text-sm text-slate-600">Pilot data is excluded from Analysis results.</p>
            <div class="mt-5 grid gap-4 xl:grid-cols-3">
                @foreach ($package['pilot_rows'] as $row)
                    @php $run = $row['latest_run']; @endphp
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900">Pilot Link</span>
                        <h3 class="mt-3 font-semibold">{{ $row['label'] }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $row['instrument']?->title ?? 'Instrument missing' }}</p>
                        <p class="mt-3"><span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $statusClass($row['status']) }}">{{ str($row['status'])->replace('_', ' ')->title() }}</span></p>
                        <p class="mt-3 text-sm text-slate-600">Test responses: <span class="font-semibold">{{ $row['test_response_count'] }}</span></p>
                        <p class="text-sm text-slate-600">Last test submission: {{ $row['last_test_submission_at'] ? \Illuminate\Support\Carbon::parse($row['last_test_submission_at'])->format('Y-m-d H:i') : 'None' }}</p>

                        @if ($row['instrument'])
                            <form method="POST" action="{{ $row['generate_route'] }}" class="mt-4">
                                @csrf
                                <button type="submit" class="w-full rounded-md bg-amber-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-600">Generate Pilot Link</button>
                            </form>
                        @else
                            <p class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">Create this instrument before generating pilot links.</p>
                        @endif

                        @if ($run)
                            <form method="POST" action="{{ route('admin.surveys.respondent-package.pilot.revoke', ['survey' => $survey, 'pilotRun' => $run]) }}" class="mt-2">
                                @csrf
                                <button type="submit" onclick="return confirm('Revoke this pilot link?')" @disabled($run->status === \App\Models\AnalysisPilotRun::STATUS_REVOKED) class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:border-slate-200 disabled:text-slate-400">Revoke Pilot Link</button>
                            </form>

                            <form method="POST" action="{{ route('admin.surveys.respondent-package.pilot.checklist', ['survey' => $survey, 'pilotRun' => $run]) }}" class="mt-4 space-y-3 rounded-md border border-slate-200 bg-white p-3">
                                @csrf
                                @method('PUT')
                                @foreach ($checkLabels as $key => $label)
                                    <label class="flex items-start gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="{{ $key }}" value="1" @checked(($row['checklist'][$key] ?? false) === true) class="mt-1 rounded border-slate-300 text-emerald-700">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                                <label class="block text-sm">
                                    <span class="font-semibold">Notes</span>
                                    <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $row['checklist']['notes'] ?? $run->notes }}</textarea>
                                </label>
                                <button type="submit" class="w-full rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Save Checklist / Mark Pilot Passed</button>
                            </form>

                            <form method="POST" action="{{ route('admin.surveys.respondent-package.pilot.fail', ['survey' => $survey, 'pilotRun' => $run]) }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="notes" value="{{ $run->notes }}">
                                <button type="submit" class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700">Mark Pilot Failed</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Test Response Management</p>
            <h2 class="mt-1 text-xl font-semibold">Clear pilot/test responses safely</h2>
            <p class="mt-2 text-sm text-slate-600">Only responses flagged as test data or excluded from analysis are cleared. Real responses are never selected by these actions.</p>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                @foreach ($package['test_response_summary'] as $summary)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="font-semibold">{{ $summary['label'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600">Test responses: <span class="font-semibold">{{ $summary['count'] }}</span></p>
                        <p class="text-sm text-slate-600">Last: {{ $summary['last_submitted_at'] ? \Illuminate\Support\Carbon::parse($summary['last_submitted_at'])->format('Y-m-d H:i') : 'None' }}</p>
                        @if ($summary['instrument'])
                            <form method="POST" action="{{ route('admin.surveys.respondent-package.test-responses.clear-target', ['survey' => $survey, 'targetSurvey' => $summary['instrument']]) }}" class="mt-4">
                                @csrf
                                <button type="submit" onclick="return confirm('Clear only test responses for this instrument? Real responses will not be deleted.')" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700">Clear Test Responses</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
            <form method="POST" action="{{ route('admin.surveys.respondent-package.test-responses.clear', ['survey' => $survey]) }}" class="mt-5">
                @csrf
                <button type="submit" onclick="return confirm('Clear all test responses for this Analysis package? Real responses will not be deleted.')" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600">Clear All Test Responses</button>
            </form>
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expert Validator Links</p>
                <div class="mt-4 space-y-3">
                    @forelse ($package['validation_links'] as $row)
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                            <p class="font-semibold">{{ $row['name'] }} <span class="font-normal text-slate-500">{{ $row['email'] }}</span></p>
                            <p class="mt-1">Status: {{ $row['status'] }} | Link stored: {{ $row['has_link'] ? 'Yes' : 'No' }}</p>
                            <p class="mt-1 text-slate-600">{{ $row['guidance'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No validator assignments yet. Open Validation or Distribution Center.</p>
                    @endforelse
                </div>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Readability Links</p>
                <div class="mt-4 space-y-3">
                    @forelse ($package['readability_links'] as $row)
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                            <p class="font-semibold">{{ $row['name'] }} <span class="font-normal text-slate-500">{{ $row['email'] }}</span></p>
                            <p class="mt-1">Status: {{ $row['status'] }} | Link stored: {{ $row['has_link'] ? 'Yes' : 'No' }}</p>
                            <p class="mt-1 text-slate-600">{{ $row['guidance'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No readability participants yet. Open Readability or Distribution Center.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </main>

    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copyTarget);
                if (! target) return;
                await navigator.clipboard.writeText(target.value);
                button.textContent = 'Copied';
                setTimeout(() => { button.textContent = button.dataset.originalLabel || 'Copy'; }, 1200);
            });
            button.dataset.originalLabel = button.textContent;
        });
    </script>
</body>
</html>
