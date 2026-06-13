@extends('layouts.public-review')

@section('title', 'Validation Submitted - MyRiset')

@section('content')
    <div class="mx-auto flex min-h-[70vh] max-w-2xl items-center">
        <section class="w-full rounded-lg border border-emerald-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Expert Validation</p>
            <h1 class="mt-3 text-3xl font-semibold">Terima kasih, hasil validasi telah dikirim.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Masukan Bapak/Ibu akan digunakan untuk perbaikan instrumen penelitian. Link ini telah selesai digunakan dan tidak dapat dipakai untuk mengirim ulang penilaian.
            </p>
        </section>
    </div>
@endsection
