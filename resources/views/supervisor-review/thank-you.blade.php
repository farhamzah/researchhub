<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Instrumen Tersimpan - MyRiset</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center px-6 py-12">
        <section class="rounded-lg border border-emerald-200 bg-white p-8 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Review instrumen selesai</p>
            <h1 class="mt-2 text-2xl font-semibold">Terima kasih, {{ $reviewer->supervisor_name }}.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Komentar dan keputusan final Anda telah disimpan sebagai bukti review pembimbing dan tetap terpisah dari validasi ahli.</p>
            @if (filled($hubUrl ?? null))<a href="{{ $hubUrl }}" class="mt-6 inline-flex rounded-lg bg-indigo-700 px-5 py-3 text-sm font-bold text-white">Kembali ke Reviewer Hub</a>@endif
        </section>
    </main>
</body>
</html>
