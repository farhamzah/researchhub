<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }}</title>
    @vite(['resources/css/app.css'])
    <style>@media print {.no-print{display:none!important}.report-sheet{box-shadow:none!important;margin:0!important}}</style>
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <header class="no-print sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
            <div><p class="font-semibold">Pratinjau laporan</p><p class="text-xs text-slate-500">Data dikunci pada snapshot versi yang direview.</p></div>
            <div class="flex flex-wrap gap-2">
                @if ($round->finalized_at)
                    <a href="{{ route('admin.surveys.supervisor-review.report.docx', ['survey' => $survey, 'round' => $round]) }}" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Unduh DOCX</a>
                @endif
                <button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Cetak</button>
            </div>
        </div>
    </header>

    <main class="report-sheet mx-auto my-6 min-h-[297mm] w-full max-w-[210mm] bg-white px-5 py-8 shadow-xl sm:px-10">
        <h1 class="text-2xl font-bold tracking-tight">{{ $report['title'] }}</h1>
        <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold">Instrumen</dt><dd>{{ $report['instrument'] }}</dd></div>
            <div><dt class="font-semibold">Kode dan versi</dt><dd>{{ $report['identifier'] ?: '—' }}</dd></div>
            <div><dt class="font-semibold">Proyek</dt><dd>{{ $report['project'] ?: '—' }}</dd></div>
            <div><dt class="font-semibold">Putaran</dt><dd>{{ $report['round'] }}</dd></div>
            <div><dt class="font-semibold">Snapshot</dt><dd>{{ $report['snapshot_taken_at']?->format('d M Y H:i') ?: '—' }}</dd></div>
            <div><dt class="font-semibold">Source berubah setelah snapshot</dt><dd>{{ $instrumentChanged ? 'Ya — laporan tetap memakai snapshot' : 'Tidak' }}</dd></div>
        </dl>

        @forelse ($report['reviewers'] as $index => $reviewer)
            <section class="mt-10 break-before-page" data-reviewer="{{ $index }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-xl font-bold">Reviewer: {{ $reviewer['name'] }}</h2><p class="text-sm text-slate-600">Keputusan akhir: {{ $reviewer['decision'] }}</p></div>
                    <div class="no-print flex gap-2">
                        <button type="button" data-copy-target="narrative-{{ $index }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Copy Narasi</button>
                        <button type="button" data-copy-target="table-{{ $index }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold">Copy Tabel</button>
                    </div>
                </div>
                <p id="narrative-{{ $index }}" class="mt-4 text-sm leading-7">{{ $reviewer['narrative'] }}</p>
                <textarea id="table-{{ $index }}" class="sr-only" aria-hidden="true">{{ $reviewer['table_tsv'] }}</textarea>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-left text-xs">
                        <thead><tr class="bg-slate-100">
                            @foreach (['Kode', 'Redaksi saat direview', 'Opsi jawaban', 'Keputusan', 'Komentar', 'Redaksi revisi'] as $heading)
                                <th class="border border-slate-300 px-2 py-2 font-semibold">{{ $heading }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($reviewer['rows'] as $row)
                                <tr class="align-top">
                                    <td class="border border-slate-300 px-2 py-2 font-mono">{{ $row['code'] }}</td>
                                    <td class="border border-slate-300 px-2 py-2">{{ $row['reviewed_wording'] }}</td>
                                    <td class="border border-slate-300 px-2 py-2">{{ $row['answer_options'] }}</td>
                                    <td class="border border-slate-300 px-2 py-2">{{ $row['decision'] }}</td>
                                    <td class="border border-slate-300 px-2 py-2">{{ $row['comment'] }}</td>
                                    <td class="border border-slate-300 px-2 py-2">{{ $row['revised_wording'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="mt-8 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Belum ada review yang dapat dilaporkan.</p>
        @endforelse
    </main>
    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget);
            await navigator.clipboard.writeText(target.value ?? target.innerText);
            const label = button.textContent;
            button.textContent = 'Tersalin';
            window.setTimeout(() => { button.textContent = label; }, 1200);
        }));
    </script>
</body>
</html>
