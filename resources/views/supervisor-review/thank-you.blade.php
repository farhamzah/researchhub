<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supervisor Review Submitted</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center px-6 py-12">
        <section class="rounded-lg border border-emerald-200 bg-white p-8 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Supervisor Review Submitted</p>
            <h1 class="mt-2 text-2xl font-semibold">Thank you, {{ $reviewer->supervisor_name }}.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Your comments and final recommendation have been stored as supervisor review evidence and remain separate from expert validation scoring.</p>
        </section>
    </main>
</body>
</html>
