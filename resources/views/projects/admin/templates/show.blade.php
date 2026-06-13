<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $template['name'] }} Template - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Template Preview</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ $template['name'] }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $template['description'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.projects.templates.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Pilih Template Lain
                </a>
                <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Projects
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Periksa kembali data project.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
            <section class="grid gap-4">
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold">Yang akan dibuat</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $template['best_for'] }}</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Milestone</p>
                            <p class="mt-1 text-2xl font-semibold">{{ count($template['milestones']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Task</p>
                            <p class="mt-1 text-2xl font-semibold">{{ count($template['tasks']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dokumen</p>
                            <p class="mt-1 text-2xl font-semibold">{{ count($template['documents']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Survey</p>
                            <p class="mt-1 text-2xl font-semibold">{{ is_array($template['survey']) ? '1' : '0' }}</p>
                        </div>
                    </div>
                </article>

                <div class="grid gap-4 lg:grid-cols-2">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">Milestones</h2>
                        <ol class="mt-4 space-y-2 text-sm text-slate-700">
                            @foreach ($template['milestones'] as $milestone)
                                <li>{{ $loop->iteration }}. {{ $milestone }}</li>
                            @endforeach
                        </ol>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">Timeline Tasks</h2>
                        <ol class="mt-4 space-y-2 text-sm text-slate-700">
                            @foreach ($template['tasks'] as $task)
                                <li>{{ $loop->iteration }}. {{ $task }}</li>
                            @endforeach
                        </ol>
                    </article>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">Starter Documents</h2>
                        <ul class="mt-4 space-y-2 text-sm text-slate-700">
                            @foreach ($template['documents'] as $document)
                                <li>{{ $document['title'] }} <span class="text-slate-500">({{ \App\Models\Document::TYPE_LABELS[$document['type']] ?? 'Other' }}, v01 draft)</span></li>
                            @endforeach
                        </ul>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold">Starter Survey & Links</h2>
                        @if (is_array($template['survey']))
                            <p class="mt-4 text-sm font-semibold text-slate-800">{{ $template['survey']['title'] }}</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                @foreach ($template['survey']['questions'] as $question)
                                    <li>{{ $question }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-4 text-sm text-slate-600">Template ini tidak membuat starter survey.</p>
                        @endif

                        @if ($template['research_links'] !== [])
                            <p class="mt-5 text-sm font-semibold text-slate-800">Research links aman</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                @foreach ($template['research_links'] as $link)
                                    <li>{{ $link['title'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                </div>
            </section>

            <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold">Check answers</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Isi detail project, lalu MyRiset akan membuat struktur starter dan membuka Alur Riset.
                </p>

                <form method="POST" action="{{ route('admin.projects.templates.store', ['template' => $template['key']]) }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="title" class="text-sm font-semibold text-slate-700">Project title</label>
                        <input id="title" name="title" value="{{ old('title', $template['default_title']) }}" required maxlength="255" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="description" class="text-sm font-semibold text-slate-700">Description optional</label>
                        <textarea id="description" name="description" rows="4" maxlength="5000" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">{{ old('description', $template['description']) }}</textarea>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="started_at" class="text-sm font-semibold text-slate-700">Start date</label>
                            <input id="started_at" name="started_at" type="date" value="{{ old('started_at', now()->toDateString()) }}" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="target_finished_at" class="text-sm font-semibold text-slate-700">Target finish</label>
                            <input id="target_finished_at" name="target_finished_at" type="date" value="{{ old('target_finished_at', now()->addDays($template['duration_days'])->toDateString()) }}" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <input type="hidden" name="include_documents" value="0">
                        <input type="hidden" name="include_survey" value="0">
                        <input type="hidden" name="include_research_links" value="0">

                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" name="include_documents" value="1" class="mt-1 rounded border-slate-300" checked>
                            <span>Buat starter documents dengan status draft, v01, dan next action.</span>
                        </label>
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" name="include_survey" value="1" class="mt-1 rounded border-slate-300" {{ is_array($template['survey']) ? 'checked' : '' }}>
                            <span>Buat starter survey/instrument jika tersedia.</span>
                        </label>
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" name="include_research_links" value="1" class="mt-1 rounded border-slate-300" checked>
                            <span>Buat starter research links aman.</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">
                        Buat Project dari Template
                    </button>
                </form>
            </aside>
        </div>
    </main>
</body>
</html>
