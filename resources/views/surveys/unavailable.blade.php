<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Tidak Tersedia - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-12">
        <section class="w-full rounded-lg border border-gray-200 bg-white p-6 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Survey MyRiset</p>
            <h1 class="mt-3 text-2xl font-semibold">{{ $title ?? 'Survey tidak tersedia' }}</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600">{{ $message ?? 'Survey ini belum menerima respons publik saat ini.' }}</p>
        </section>
    </main>
</body>
</html>
