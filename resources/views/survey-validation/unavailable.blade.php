@extends('layouts.public-review')

@section('title', 'Validation Link Unavailable - MyRiset')

@section('content')
    <div class="mx-auto flex min-h-[70vh] max-w-2xl items-center">
        <section class="w-full rounded-lg border border-amber-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">MyRiset Expert Validation</p>
            <h1 class="mt-3 text-3xl font-semibold">Link validasi tidak aktif.</h1>
            <p class="mt-2 text-sm font-semibold text-amber-700">Link validasi tidak tersedia</p>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Link ini mungkin sudah kedaluwarsa, sudah digunakan, atau telah dicabut oleh pengelola riset. Silakan hubungi peneliti atau pengelola riset untuk mendapatkan link baru.
            </p>
        </section>
    </div>
@endsection
