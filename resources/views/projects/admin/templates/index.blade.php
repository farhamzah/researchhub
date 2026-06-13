<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Templates - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">MyRiset Project Templates</p>
                <h1 class="mt-2 text-3xl font-semibold">Buat Project dari Template</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Pilih struktur riset awal agar milestone, dokumen, task, dan instrumen starter langsung siap dipakai.
                </p>
            </div>
            <a href="{{ route('filament.admin.resources.projects.research-projects.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Kembali ke Projects
            </a>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex h-full flex-col">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Template</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-950">{{ $template['name'] }}</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $template['description'] }}</p>
                        </div>

                        <dl class="mt-4 grid gap-2 text-sm">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Best for</dt>
                                <dd class="mt-1 text-slate-700">{{ $template['best_for'] }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Milestone</dt>
                                    <dd class="mt-1 font-semibold">{{ count($template['milestones']) }}</dd>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dokumen</dt>
                                    <dd class="mt-1 font-semibold">{{ count($template['documents']) }}</dd>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Survey</dt>
                                    <dd class="mt-1 font-semibold">{{ is_array($template['survey']) ? 'Ya' : 'Tidak' }}</dd>
                                </div>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('admin.projects.templates.show', ['template' => $template['key']]) }}" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">
                                Gunakan Template
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
    </main>
</body>
</html>
