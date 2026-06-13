@extends('layouts.public-review')

@php
    $statusLabel = 'Aktif';
@endphp

@section('title', 'Supervision Review - MyRiset')

@section('content')
    <section data-ui="myriset-page-header" class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Supervision Review</p>
                <h1 class="mt-2 text-3xl font-semibold">Review Bimbingan Riset</h1>
                <p class="mt-3 text-lg font-semibold text-slate-800">{{ $session->title }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $project->title }}</p>
            </div>
            <x-myriset.status-badge status="active" :label="'Status: '.$statusLabel" />
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $reviewLink->recipientDisplayName() }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $reviewLink->recipientDisplayRole() }}</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Pertemuan</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ \App\Models\SupervisionSession::MEETING_TYPE_LABELS[$session->meeting_type] ?? $session->meeting_type }}</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Batas Akses</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $reviewLink->expires_at?->format('Y-m-d H:i') ?? 'Tidak ada batas khusus' }}</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resource Terlihat</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $session->visibleResources->count() }} resource</p>
            </div>
        </div>
    </section>

    <section data-ui="myriset-section-card" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-950">
        <h2 class="text-lg font-semibold">Petunjuk bimbingan</h2>
        <p class="mt-2">Tuliskan masukan utama untuk membantu peneliti menentukan revisi berikutnya.</p>
        <ul class="mt-3 list-disc space-y-1 pl-5">
            <li>Periksa agenda, progress, pertanyaan, dan rencana berikutnya.</li>
            <li>Berikan keputusan atau rekomendasi umum yang paling sesuai.</li>
            <li>Setelah dikirim, masukan tidak dapat diubah melalui link ini.</li>
        </ul>
    </section>

    <section class="mb-6 grid gap-4 lg:grid-cols-2">
        @foreach ([
            'Agenda' => $session->agenda ?: 'No agenda provided.',
            'Progress Report' => $session->progress_report ?: 'No progress report provided.',
            'Questions for Supervisor' => $session->questions ?: 'No questions provided.',
            'Requested Feedback' => $session->requested_feedback ?: 'No specific feedback requested.',
            'Next Plan' => $session->next_plan ?: 'No next plan provided.',
        ] as $label => $value)
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm @if ($label === 'Next Plan') lg:col-span-2 @endif">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $value }}</p>
            </article>
        @endforeach
    </section>

    @if ($session->visibleResources->isNotEmpty())
        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Shared Resources</h2>
                    <p class="mt-1 text-sm text-slate-600">Hanya resource yang ditandai terlihat untuk supervisor yang ditampilkan di halaman ini.</p>
                </div>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-semibold text-slate-700">{{ $session->visibleResources->count() }} visible</span>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                @foreach ($session->visibleResources as $resource)
                    <article class="rounded-md border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-950">{{ $resource->displayTitle() }}</h3>
                            <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">{{ $resource->typeLabel() }}</span>
                        </div>
                        @if ($resource->description)
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $resource->description }}</p>
                        @endif
                        @if ($resource->notes)
                            <p class="mt-2 whitespace-pre-line text-xs leading-5 text-slate-500">{{ $resource->notes }}</p>
                        @endif
                        @if ($resource->safePublicUrl())
                            <a href="{{ $resource->safePublicUrl() }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                                Open resource
                            </a>
                        @else
                            <p class="mt-3 text-xs font-semibold text-slate-500">Metadata only. File privat tidak dibuka melalui halaman publik ini.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($errors->any())
        <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-5 text-sm leading-6 text-red-950" role="alert">
            <h2 class="text-lg font-semibold">Please review the feedback form.</h2>
            <p class="mt-1">Periksa bagian berikut sebelum mengirim masukan.</p>
            <ul class="mt-3 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section data-ui="myriset-section-card" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-semibold">Submit Supervisor Feedback</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">Tuliskan masukan utama untuk membantu peneliti menentukan revisi berikutnya.</p>
        <form method="POST" class="mt-5 grid gap-4">
            @csrf
            <div>
                <label class="text-sm font-semibold text-slate-700" for="decision">Decision / recommendation</label>
                <select id="decision" name="decision" required class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                    <option value="">Pilih rekomendasi</option>
                    @foreach ($decisionLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('decision') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('decision')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700" for="general_feedback">General feedback</label>
                <textarea id="general_feedback" name="general_feedback" required rows="5" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('general_feedback') }}</textarea>
                <p class="mt-1 text-sm text-slate-500">Masukan utama untuk kualitas akademik, fokus revisi, atau keputusan bimbingan.</p>
                @error('general_feedback')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700" for="revision_notes">Revision notes</label>
                <textarea id="revision_notes" name="revision_notes" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('revision_notes') }}</textarea>
                @error('revision_notes')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700" for="recommended_next_steps">Recommended next steps</label>
                <textarea id="recommended_next_steps" name="recommended_next_steps" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('recommended_next_steps') }}</textarea>
                @error('recommended_next_steps')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-semibold text-slate-700" for="supervisor_note">Supervisor note</label>
                <textarea id="supervisor_note" name="supervisor_note" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('supervisor_note') }}</textarea>
                @error('supervisor_note')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm leading-6 text-emerald-950">Setelah dikirim, peneliti dapat melihat masukan ini dan membuat tindak lanjut revisi di MyRiset.</p>
                <button class="mt-3 w-full rounded-md bg-emerald-700 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-emerald-800">Kirim Masukan Bimbingan</button>
            </div>
        </form>
    </section>
@endsection
