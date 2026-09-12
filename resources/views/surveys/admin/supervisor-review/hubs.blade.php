<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tautan Reviewer Hub - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-indigo-700">Review Pembimbing</p>
                    <h1 class="mt-2 text-3xl font-bold">Tautan Reviewer Hub</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Satu tautan aman untuk setiap pembimbing, mencakup instrumen S01, S02, dan S03. Tautan baru hanya ditampilkan satu kali.</p>
                </div>
                <a href="{{ route('admin.surveys.supervisor-review.index', ['survey' => $survey]) }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Kembali</a>
            </div>

            @if (session('status') === 'supervisor-reviewer-hub-link-revoked')
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">Tautan Reviewer Hub telah dicabut.</div>
            @endif

            @if (session('generated_supervisor_reviewer_hub_url'))
                <div class="mt-6 rounded-xl border border-emerald-300 bg-emerald-50 p-4" data-generated-hub-link>
                    <p class="text-sm font-bold text-emerald-950">Tautan baru untuk {{ session('generated_supervisor_reviewer_hub_code') }}</p>
                    <p class="mt-1 text-xs text-emerald-800">Salin sekarang. Demi keamanan, tautan lengkap tidak dapat dilihat kembali setelah halaman ditutup.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input id="generated-hub-url" readonly value="{{ session('generated_supervisor_reviewer_hub_url') }}" class="min-w-0 flex-1 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm">
                        <button type="button" data-copy-hub-link class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Salin tautan</button>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">{{ $errors->first() }}</div>
            @endif

            <div class="mt-8 space-y-4">
                @forelse ($reviewerGroups as $code => $reviewers)
                    @php
                        $hub = $hubs->get($code);
                        $active = $hub?->isAccessible() ?? false;
                        $status = $hub?->overallStatus() ?? \App\Models\SurveySupervisorReviewerHub::STATUS_NOT_STARTED;
                        $statusLabel = match ($status) {
                            \App\Models\SurveySupervisorReviewerHub::STATUS_COMPLETED => 'Selesai',
                            \App\Models\SurveySupervisorReviewerHub::STATUS_IN_PROGRESS => 'Dalam proses',
                            default => 'Belum dimulai',
                        };
                    @endphp
                    <article class="rounded-xl border border-slate-200 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold">{{ $reviewers->first()->supervisor_name }}</h2>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $code }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">3 instrumen · Status review: {{ $statusLabel }}</p>
                                <p class="mt-1 text-sm font-semibold {{ $active ? 'text-emerald-700' : 'text-amber-700' }}">{{ $active ? 'Tautan aktif sampai '.$hub->expires_at->timezone(config('app.timezone'))->format('d M Y H:i') : 'Belum ada tautan aktif' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.surveys.supervisor-review.hubs.generate', ['survey' => $survey]) }}">
                                    @csrf
                                    <input type="hidden" name="supervisor_code" value="{{ $code }}">
                                    <button class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white">{{ $active ? 'Perbarui tautan' : 'Buat tautan' }}</button>
                                </form>
                                @if ($active)
                                    <form method="POST" action="{{ route('admin.surveys.supervisor-review.hubs.revoke', ['survey' => $survey, 'hub' => $hub]) }}">
                                        @csrf
                                        <button class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-bold text-red-700">Cabut tautan</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">Assignment lengkap S01, S02, dan S03 belum tersedia untuk pembimbing.</div>
                @endforelse
            </div>
        </section>
    </main>
    <script>
        document.querySelector('[data-copy-hub-link]')?.addEventListener('click', async (event) => {
            const input = document.querySelector('#generated-hub-url');
            await navigator.clipboard.writeText(input.value);
            event.currentTarget.textContent = 'Tersalin';
        });
    </script>
</body>
</html>
