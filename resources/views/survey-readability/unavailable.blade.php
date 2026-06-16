@extends('layouts.public-review')

@section('title', 'Readability Link Unavailable - MyRiset')

@section('content')
    <section class="rounded-lg border border-red-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wide text-red-700">Readability link unavailable</p>
        <h1 class="mt-3 text-2xl font-semibold">This readability test link is not available.</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">The round may be closed, the link may be revoked, or the feedback may already have been submitted.</p>
    </section>
@endsection
