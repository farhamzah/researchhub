@php
    use App\Models\AnalysisDocumentPackage;

    $package = $packageData['package'];
    $statusClass = fn (string $status): string => match ($status) {
        AnalysisDocumentPackage::STATUS_FINAL => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        AnalysisDocumentPackage::STATUS_REVIEWED => 'border-blue-200 bg-blue-50 text-blue-800',
        default => 'border-amber-200 bg-amber-50 text-amber-900',
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Official Analysis Instrument Package - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section data-ui="myriset-page-header" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-4xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Analysis</p>
                    <h1 class="mt-2 text-3xl font-semibold">Official Analysis Instrument Package</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $survey->project?->title ?? 'No project' }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Paket resmi instrumen dan laporan tahap Analysis ADDIE PharmVR untuk review pembimbing/promotor.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($packageData['nav'] as $label => $url)
                        <a href="{{ $url }}" class="{{ $label === 'Analysis Package' ? 'bg-emerald-700 text-white hover:bg-emerald-600' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }} rounded-md px-4 py-2 text-sm font-semibold shadow-sm">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </section>

        @if (session('status'))
            <section class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the package metadata.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-6 grid gap-6 lg:grid-cols-[1fr_1.25fr]">
            <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Package Status</p>
                        <h2 class="mt-1 text-xl font-semibold">{{ AnalysisDocumentPackage::STATUS_LABELS[$package->status] ?? $package->status }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $package->finalized_at ? 'Finalized at '.$package->finalized_at->format('d M Y H:i') : 'Editable package metadata and live preview.' }}</p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClass($package->status) }}">{{ AnalysisDocumentPackage::STATUS_LABELS[$package->status] ?? $package->status }}</span>
                </div>

                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <a href="#package-metadata" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Edit Metadata</a>
                    <a href="#package-preview" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Preview Package</a>
                    <a href="{{ route('admin.surveys.analysis-package.print', ['survey' => $survey]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-center text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Print Package</a>
                    <a href="{{ route('admin.surveys.analysis-package.export-html', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Export HTML</a>
                    <a href="{{ route('admin.surveys.analysis-package.export-doc', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Export DOC</a>
                    <form method="POST" action="{{ route('admin.surveys.analysis-package.finalize', ['survey' => $survey]) }}">
                        @csrf
                        <button type="submit" onclick="return confirm('Finalizing creates a snapshot for documentation. Later data changes will not alter this finalized snapshot unless the package is reopened or a new version is created. Continue?')" class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Finalize Package</button>
                    </form>
                </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Readiness</p>
                <h2 class="mt-1 text-xl font-semibold">{{ $packageData['collection']['readiness']['status'] }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $packageData['readiness_recommendation'] }}</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Minimum Met</p>
                        <p class="mt-1 text-xl font-semibold">{{ $packageData['collection']['readiness']['minimum_met_count'] }} / {{ $packageData['collection']['readiness']['required_count'] }}</p>
                    </div>
                    <div class="rounded-md bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Target Met</p>
                        <p class="mt-1 text-xl font-semibold">{{ $packageData['collection']['readiness']['target_met_count'] }} / {{ $packageData['collection']['readiness']['required_count'] }}</p>
                    </div>
                </div>
            </article>
        </section>

        @if ($packageData['missing_items'])
            <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                <p class="font-semibold">Missing or incomplete items before final review</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($packageData['missing_items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section id="package-metadata" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Package Metadata</p>
            <h2 class="mt-1 text-xl font-semibold">Identitas dokumen resmi</h2>
            <form method="POST" action="{{ route('admin.surveys.analysis-package.update', ['survey' => $survey]) }}" class="mt-5 grid gap-4 lg:grid-cols-2">
                @csrf
                @method('PUT')
                <label class="block text-sm lg:col-span-2">
                    <span class="font-semibold text-slate-700">Title</span>
                    <input name="title" value="{{ old('title', $package->title) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Document Code</span>
                    <input name="document_code" value="{{ old('document_code', $package->document_code) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Version</span>
                    <input name="version" value="{{ old('version', $package->version) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Document Date</span>
                    <input type="date" name="document_date" value="{{ old('document_date', $package->document_date?->toDateString()) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Stage</span>
                    <input name="stage" value="{{ old('stage', $package->stage) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Researcher Name</span>
                    <input name="researcher_name" value="{{ old('researcher_name', $package->researcher_name) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">NPM/NIDN/Nomor Identitas Peneliti</span>
                    <input name="researcher_identifier" value="{{ old('researcher_identifier', $package->researcher_identifier) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Institution</span>
                    <input name="institution" value="{{ old('institution', $package->institution) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Study Program</span>
                    <input name="study_program" value="{{ old('study_program', $package->study_program) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Ketua Promotor</span>
                    <input name="promoter_name" value="{{ old('promoter_name', $package->promoter_name) }}" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Status</span>
                    <select name="status" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                        @foreach (AnalysisDocumentPackage::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $package->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm lg:col-span-2">
                    <span class="font-semibold text-slate-700">Co-Promotor 1 / Co-Promotor 2</span>
                    <textarea name="co_promoter_names" rows="3" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('co_promoter_names', $package->co_promoter_names) }}</textarea>
                </label>
                <label class="block text-sm lg:col-span-2">
                    <span class="font-semibold text-slate-700">Purpose Text</span>
                    <textarea name="purpose_text" rows="4" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('purpose_text', $package->purpose_text) }}</textarea>
                </label>
                <label class="block text-sm lg:col-span-2">
                    <span class="font-semibold text-slate-700">Notes</span>
                    <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('notes', $package->notes) }}</textarea>
                </label>
                <div class="lg:col-span-2">
                    <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Save Metadata</button>
                </div>
            </form>
        </section>

        <section id="package-preview" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Live Preview</p>
                    <h2 class="mt-1 text-xl font-semibold">Supervisor review package</h2>
                </div>
                <a href="{{ route('admin.surveys.analysis-package.print', ['survey' => $survey]) }}" target="_blank" class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-50">Open Print View</a>
            </div>
            @include('surveys.admin.analysis-package._document', ['packageData' => $packageData, 'survey' => $survey])
        </section>
    </main>
</body>
</html>
