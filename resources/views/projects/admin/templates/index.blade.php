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
        <x-myriset.page-header
            eyebrow="MyRiset Project Templates"
            title="Buat Project dari Template"
            description="Pilih struktur riset awal agar milestone, dokumen, task, dan instrumen starter langsung siap dipakai."
        >
            <x-slot:actions>
                <x-myriset.action-link :href="route('filament.admin.resources.projects.research-projects.index')">
                Kembali ke Projects
                </x-myriset.action-link>
            </x-slot:actions>
        </x-myriset.page-header>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <x-myriset.section-card class="h-full">
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
                            <x-myriset.action-link :href="route('admin.projects.templates.show', ['template' => $template['key']])" variant="primary">
                                Gunakan Template
                            </x-myriset.action-link>
                        </div>
                    </div>
                </x-myriset.section-card>
            @endforeach
        </section>
    </main>
</body>
</html>
