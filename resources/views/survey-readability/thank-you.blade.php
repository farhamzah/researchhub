@extends('layouts.public-review')

@section('title', 'Readability Feedback Submitted - MyRiset')

@section('content')
    <section class="rounded-lg border border-emerald-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Readability feedback submitted</p>
        <h1 class="mt-3 text-2xl font-semibold">Thank you.</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Your readability feedback has been recorded for instrument revision documentation. This link cannot be submitted again unless the researcher regenerates it.</p>
    </section>
@endsection
