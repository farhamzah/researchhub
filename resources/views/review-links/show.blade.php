<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Dokumen - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto min-h-screen max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">MyRiset</p>
            <h1 class="mt-2 text-3xl font-semibold">Review Dokumen</h1>
        </header>

        @if (! $resolution->allowed && ! $resolution->requiresPassword)
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Link review tidak tersedia.</h2>
                <p class="mt-2 text-sm text-gray-600">Link mungkin tidak valid, sudah kedaluwarsa, dicabut, atau tidak lagi aktif.</p>
            </section>
        @elseif ($resolution->requiresPassword)
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Password diperlukan</h2>
                <p class="mt-2 text-sm text-gray-600">Masukkan password review untuk melanjutkan.</p>

                <form method="POST" action="{{ route('review.password', ['token' => $token]) }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">
                        Lanjutkan
                    </button>
                </form>
            </section>
        @else
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                    Perubahan tersimpan.
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ $document?->category?->name }}</p>
                        <h2 class="mt-1 text-2xl font-semibold">{{ $document?->title }}</h2>
                        <p class="mt-2 text-sm text-gray-600">{{ $document?->description ?: 'Belum ada deskripsi.' }}</p>
                    </div>
                    <div class="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                        {{ $document?->status }}
                    </div>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-md border border-gray-100 p-4">
                        <dt class="text-xs font-medium uppercase text-gray-500">Versi</dt>
                        <dd class="mt-2 text-sm font-medium">{{ $version?->version_number ? 'v'.$version->version_number : 'Belum ada versi' }}</dd>
                    </div>
                    <div class="rounded-md border border-gray-100 p-4">
                        <dt class="text-xs font-medium uppercase text-gray-500">File</dt>
                        <dd class="mt-2 text-sm font-medium">{{ $version?->original_file_name ?: $version?->file_name ?: 'Belum ada metadata file' }}</dd>
                    </div>
                    <div class="rounded-md border border-gray-100 p-4">
                        <dt class="text-xs font-medium uppercase text-gray-500">Reviewer</dt>
                        <dd class="mt-2 text-sm font-medium">{{ $reviewLink?->reviewer_name ?: 'Reviewer tamu' }}</dd>
                    </div>
                </dl>

                @if ($reviewLink?->allows('download'))
                    <div class="mt-6">
                        <a href="{{ route('review.download', ['token' => $token]) }}" class="inline-flex rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">
                            Unduh Dokumen
                        </a>
                    </div>
                @endif
            </section>

            @if ($versions->isNotEmpty())
                <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold">Riwayat Versi</h3>
                    <ul class="mt-4 divide-y divide-gray-100">
                        @foreach ($versions as $item)
                            <li class="py-3 text-sm">
                                v{{ $item->version_number }} - {{ $item->original_file_name ?: $item->file_name }} - {{ $item->created_at->format('Y-m-d H:i') }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($reviewLink?->allows('comment'))
                <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold">Komentar</h3>
                    <form method="POST" action="{{ route('review.comments.store', ['token' => $token]) }}" class="mt-4 space-y-4">
                        @csrf
                        <textarea name="comment" rows="5" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                        <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">Kirim Komentar</button>
                    </form>
                </section>
            @endif

            @if ($reviewLink?->allows('approve') || $reviewLink?->allows('request_revision'))
                <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold">Keputusan Review</h3>
                    <form method="POST" action="{{ route('review.decision.store', ['token' => $token]) }}" class="mt-4 space-y-4">
                        @csrf
                        <textarea name="notes" rows="4" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Catatan tambahan opsional"></textarea>
                        <div class="flex flex-wrap gap-3">
                            @if ($reviewLink?->allows('approve'))
                                <button name="decision" value="approved" type="submit" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-600">Setujui</button>
                            @endif
                            @if ($reviewLink?->allows('request_revision'))
                                <button name="decision" value="revision_required" type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">Minta Revisi</button>
                            @endif
                        </div>
                    </form>
                </section>
            @endif
        @endif
    </main>
</body>
</html>
