@extends('layouts.public-review')

@section('title', 'Feedback Submitted - MyRiset')

@section('content')
    <div class="mx-auto flex min-h-[70vh] max-w-2xl items-center">
        <section class="w-full rounded-lg border border-emerald-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Supervision Review</p>
            <h1 class="mt-3 text-3xl font-semibold">Terima kasih, masukan bimbingan telah dikirim.</h1>
            <p class="mt-2 text-sm font-semibold text-emerald-700">Feedback submitted</p>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Peneliti dapat melihat masukan ini dan membuat tindak lanjut revisi di MyRiset. Link ini telah selesai digunakan dan tidak dapat dipakai untuk mengirim ulang masukan.
            </p>
        </section>
    </div>
@endsection
