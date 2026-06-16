@php
    use App\Models\SurveyDistributionBatch;

    $statusClass = fn (string $status): string => match ($status) {
        'ready', 'completed', 'sent_manually' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'draft', 'missing' => 'border-amber-200 bg-amber-50 text-amber-900',
        'closed', 'revoked' => 'border-slate-200 bg-slate-50 text-slate-700',
        default => 'border-blue-200 bg-blue-50 text-blue-800',
    };
    $copyId = fn (string $prefix, string $id): string => $prefix.'_'.str_replace('-', '_', $id);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Research Distribution Center - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section data-ui="myriset-page-header" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                    <h1 class="mt-2 text-3xl font-semibold">Research Distribution Center</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Kelola link, pesan undangan, status distribusi manual, deadline, dan paket laporan tahap Analysis ADDIE PharmVR.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Builder</a>
                    <a href="{{ route('admin.surveys.validation.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Validation</a>
                    <a href="{{ route('admin.surveys.readability.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Readability</a>
                    <a href="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Analysis Dashboard</a>
                    <a href="{{ route('admin.surveys.distribution.report', ['survey' => $survey]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Printable Package</a>
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Back to Surveys</a>
                </div>
            </div>
        </section>

        @if (session('status'))
            <section class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the distribution form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (session('generated_validation_url') || session('generated_readability_url'))
            <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                <p class="font-semibold">Copy this generated link now.</p>
                <p class="mt-1">Raw validation/readability tokens are not stored and cannot be recovered later.</p>
                @php $generatedUrl = session('generated_validation_url') ?: session('generated_readability_url'); @endphp
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <input id="generated_public_url" readonly value="{{ $generatedUrl }}" class="min-w-0 flex-1 rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-xs text-slate-900 shadow-sm">
                    <button type="button" data-copy-target="generated_public_url" class="rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-600">Copy Link</button>
                </div>
            </section>
        @endif

        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($distribution['overview'] as $card)
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-lg font-semibold text-slate-950">{{ $card['value'] }}</p>
                    <span class="mt-3 inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass($card['status']) }}">{{ str_replace('_', ' ', $card['status']) }}</span>
                </article>
            @endforeach
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Instrument Links</p>
                    <h2 class="mt-1 text-xl font-semibold">Mahasiswa, dosen, dan praktisi</h2>
                    <p class="mt-1 text-sm text-slate-600">Public survey link aman untuk responden. Jika belum published, link akan membuka unavailable state.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                @foreach ($distribution['instruments'] as $panel)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $panel['label'] }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ $panel['survey']?->title ?? 'Instrument missing' }}</h3>
                            </div>
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass($panel['is_ready'] ? 'ready' : ($panel['survey'] ? 'draft' : 'missing')) }}">{{ $panel['is_ready'] ? 'ready' : ($panel['survey'] ? 'draft' : 'missing') }}</span>
                        </div>

                        @if ($panel['survey'])
                            <dl class="mt-4 grid gap-2 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Intro</dt><dd class="font-semibold">{{ $panel['intro_complete'] ? 'complete' : 'incomplete' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Consent</dt><dd class="font-semibold">{{ $panel['consent_required'] ? 'yes' : 'no' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Questions</dt><dd class="font-semibold">{{ $panel['question_count'] }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Responses</dt><dd class="font-semibold">{{ $panel['response_count'] }}</dd></div>
                            </dl>

                            <div class="mt-4 space-y-3">
                                @php $linkId = $copyId('instrument_link', $panel['audience']); @endphp
                                <input id="{{ $linkId }}" readonly value="{{ $panel['link'] }}" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-900 shadow-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" data-copy-target="{{ $linkId }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-600">Copy Link</button>
                                    <a href="{{ $panel['link'] }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Open Link</a>
                                    <a href="{{ $panel['builder_route'] }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Builder</a>
                                    <a href="{{ $panel['responses_route'] }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Responses</a>
                                </div>

                                @php
                                    $waId = $copyId('instrument_wa', $panel['audience']);
                                    $emailId = $copyId('instrument_email', $panel['audience']);
                                @endphp
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $waId }}">WhatsApp message</label>
                                <textarea id="{{ $waId }}" readonly rows="5" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs leading-5 shadow-sm">{{ $panel['whatsapp_message'] }}</textarea>
                                <button type="button" data-copy-target="{{ $waId }}" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Copy WhatsApp Message</button>

                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $emailId }}">Email message</label>
                                <textarea id="{{ $emailId }}" readonly rows="7" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs leading-5 shadow-sm">{{ $panel['email_message'] }}</textarea>
                                <button type="button" data-copy-target="{{ $emailId }}" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Copy Email Message</button>
                            </div>
                        @elseif ($panel['create_route'])
                            <form method="POST" action="{{ $panel['create_route'] }}" class="mt-5">
                                @csrf
                                <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">{{ $panel['missing_cta'] }}</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Expert Validation Links</p>
            <h2 class="mt-1 text-xl font-semibold">Validator ahli</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">{{ $distribution['tokenSafetyNotice'] }}</p>

            <div class="mt-5 space-y-5">
                @forelse ($distribution['validation']['rounds'] as $roundPanel)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-slate-950">{{ $roundPanel['round']->title }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $roundPanel['round']->instructions ?: 'No custom instructions.' }}</p>
                            </div>
                            <a href="{{ $roundPanel['report_route'] }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Validation Report</a>
                        </div>

                        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Validator</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Link</th>
                                        <th class="px-4 py-3">Invitation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($roundPanel['assignments'] as $assignmentPanel)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <p class="font-semibold">{{ $assignmentPanel['name'] }}</p>
                                                <p class="text-xs text-slate-500">{{ $assignmentPanel['email'] ?: 'No email recorded' }}</p>
                                            </td>
                                            <td class="px-4 py-3">{{ $assignmentPanel['status_label'] }}</td>
                                            <td class="px-4 py-3">
                                                @if (session('generated_validation_assignment_id') === $assignmentPanel['assignment']->getKey() && session('generated_validation_url'))
                                                    <input id="validation_url_{{ $assignmentPanel['assignment']->id }}" readonly value="{{ session('generated_validation_url') }}" class="mb-2 block w-full min-w-80 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 font-mono text-xs">
                                                    <button type="button" data-copy-target="validation_url_{{ $assignmentPanel['assignment']->id }}" class="mb-2 rounded-md bg-amber-700 px-3 py-2 text-xs font-semibold text-white">Copy New Link</button>
                                                @else
                                                    <p class="mb-2 text-xs text-slate-500">{{ $assignmentPanel['has_token'] ? $distribution['tokenSafetyNotice'] : 'No link generated yet.' }}</p>
                                                @endif
                                                <div class="flex flex-wrap gap-2">
                                                    <form method="POST" action="{{ $assignmentPanel['generate_route'] }}">
                                                        @csrf
                                                        <button type="submit" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">{{ $assignmentPanel['has_token'] ? 'Regenerate Link' : 'Generate Link' }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ $assignmentPanel['revoke_route'] }}">
                                                        @csrf
                                                        <button type="submit" onclick="return confirm('Revoke this validation link?')" @disabled(! $assignmentPanel['can_revoke']) class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:border-slate-200 disabled:text-slate-400">Revoke Link</button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                @php $validatorMsgId = 'validator_msg_'.$assignmentPanel['assignment']->id; @endphp
                                                <textarea id="{{ $validatorMsgId }}" readonly rows="5" class="block w-full min-w-80 rounded-md border border-slate-300 bg-white px-3 py-2 text-xs leading-5 shadow-sm">{{ $assignmentPanel['whatsapp_message'] }}</textarea>
                                                <button type="button" data-copy-target="{{ $validatorMsgId }}" class="mt-2 rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Copy Validator Invitation</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No validators assigned yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                @empty
                    <x-myriset.empty-state title="No validation rounds yet" description="Create an expert validation round before distributing validator links." />
                @endforelse
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Readability Links</p>
            <h2 class="mt-1 text-xl font-semibold">Peserta uji keterbacaan</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">{{ $distribution['tokenSafetyNotice'] }}</p>

            <div class="mt-5 space-y-5">
                @forelse ($distribution['readability']['rounds'] as $roundPanel)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-slate-950">{{ $roundPanel['round']->title }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $roundPanel['round']->instructions ?: 'No custom instructions.' }}</p>
                            </div>
                            <a href="{{ $roundPanel['report_route'] }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Readability Report</a>
                        </div>

                        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Participant</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Link</th>
                                        <th class="px-4 py-3">Invitation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($roundPanel['participants'] as $participantPanel)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <p class="font-semibold">{{ $participantPanel['name'] }}</p>
                                                <p class="text-xs text-slate-500">{{ $participantPanel['email'] ?: 'No email recorded' }}</p>
                                            </td>
                                            <td class="px-4 py-3">{{ $participantPanel['status_label'] }}</td>
                                            <td class="px-4 py-3">
                                                @if (session('generated_readability_participant_id') === $participantPanel['participant']->getKey() && session('generated_readability_url'))
                                                    <input id="readability_url_{{ $participantPanel['participant']->id }}" readonly value="{{ session('generated_readability_url') }}" class="mb-2 block w-full min-w-80 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 font-mono text-xs">
                                                    <button type="button" data-copy-target="readability_url_{{ $participantPanel['participant']->id }}" class="mb-2 rounded-md bg-amber-700 px-3 py-2 text-xs font-semibold text-white">Copy New Link</button>
                                                @else
                                                    <p class="mb-2 text-xs text-slate-500">{{ $participantPanel['has_token'] ? $distribution['tokenSafetyNotice'] : 'No link generated yet.' }}</p>
                                                @endif
                                                <div class="flex flex-wrap gap-2">
                                                    <form method="POST" action="{{ $participantPanel['generate_route'] }}">
                                                        @csrf
                                                        <button type="submit" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">{{ $participantPanel['has_token'] ? 'Regenerate Link' : 'Generate Link' }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ $participantPanel['revoke_route'] }}">
                                                        @csrf
                                                        <button type="submit" onclick="return confirm('Revoke this readability link?')" @disabled(! $participantPanel['can_revoke']) class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:border-slate-200 disabled:text-slate-400">Revoke Link</button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                @php $readabilityMsgId = 'readability_msg_'.$participantPanel['participant']->id; @endphp
                                                <textarea id="{{ $readabilityMsgId }}" readonly rows="5" class="block w-full min-w-80 rounded-md border border-slate-300 bg-white px-3 py-2 text-xs leading-5 shadow-sm">{{ $participantPanel['whatsapp_message'] }}</textarea>
                                                <button type="button" data-copy-target="{{ $readabilityMsgId }}" class="mt-2 rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">Copy Readability Invitation</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No readability participants yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                @empty
                    <x-myriset.empty-state title="No readability rounds yet" description="Create a readability round before distributing pilot links." />
                @endforelse
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Supervisor Review Package</p>
            <h2 class="mt-1 text-xl font-semibold">Pembimbing / Promotor</h2>
            <p class="mt-1 text-sm text-slate-600">Link admin memerlukan login. Untuk pembimbing tanpa akun, gunakan PDF/printable report.</p>
            <div class="mt-5 grid gap-4 lg:grid-cols-[0.45fr_0.55fr]">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                    <h3 class="font-semibold">Printable and admin links</h3>
                    <div class="mt-3 space-y-2 text-sm">
                        @foreach ($distribution['supervisor']['links'] as $label => $url)
                            <div class="rounded-md border border-slate-200 bg-white p-3">
                                <p class="font-semibold">{{ $label }}</p>
                                <a href="{{ $url }}" target="_blank" class="mt-1 break-all text-xs text-blue-700">{{ $url }}</a>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label for="supervisor_package_message" class="block text-sm font-semibold text-slate-700">Copyable supervisor package text</label>
                    <textarea id="supervisor_package_message" readonly rows="15" class="mt-2 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm leading-6 shadow-sm">{{ $distribution['supervisor']['message'] }}</textarea>
                    <button type="button" data-copy-target="supervisor_package_message" class="mt-3 rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Copy Supervisor Package</button>
                </div>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Manual Tracking</p>
            <h2 class="mt-1 text-xl font-semibold">Distribution batches</h2>
            <p class="mt-1 text-sm text-slate-600">Catat status manual, deadline, dan follow-up note. Tidak ada pesan otomatis yang dikirim dari fitur ini.</p>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach ($distribution['audienceLabels'] as $audience => $label)
                    @php $batch = $distribution['batches'][$audience] ?? null; @endphp
                    <form method="POST" action="{{ route('admin.surveys.distribution.batches.update', ['survey' => $survey, 'audience' => $audience]) }}" class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                        @csrf
                        @method('PUT')
                        <h3 class="font-semibold text-slate-950">{{ $label }}</h3>
                        @if ($batch['deadline_state'] ?? null)
                            <p class="mt-1 text-xs font-semibold text-amber-800">{{ $batch['deadline_state'] }}</p>
                        @endif
                        <div class="mt-4 grid gap-3">
                            <input name="title" value="{{ old('title', $batch['title'] ?? $label.' Distribution') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <input name="message_subject" value="{{ old('message_subject', $batch['message_subject'] ?? 'Undangan '.$label.' - '.$survey->title) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input name="deadline" type="date" value="{{ old('deadline', isset($batch['deadline']) && $batch['deadline'] ? $batch['deadline']->toDateString() : '') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                                <select name="status" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                                    @foreach ($distribution['statuses'] as $status => $statusLabel)
                                        <option value="{{ $status }}" @selected(old('status', $batch['status'] ?? SurveyDistributionBatch::STATUS_DRAFT) === $status)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <textarea name="message_body" rows="3" placeholder="Custom message body optional" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm leading-6 shadow-sm">{{ old('message_body', $batch['message_body'] ?? '') }}</textarea>
                            <textarea name="notes" rows="3" placeholder="Manual follow-up note" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm leading-6 shadow-sm">{{ old('notes', $batch['notes'] ?? '') }}</textarea>
                            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save Tracking</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-copy-target]');
            if (! button) {
                return;
            }

            const target = document.getElementById(button.dataset.copyTarget);
            if (! target) {
                return;
            }

            target.select?.();
            navigator.clipboard?.writeText(target.value || target.textContent || '').then(() => {
                button.dataset.originalText = button.dataset.originalText || button.textContent;
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = button.dataset.originalText;
                }, 1400);
            });
        });
    </script>
</body>
</html>
