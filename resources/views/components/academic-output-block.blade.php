@props([
    'title',
    'description' => null,
    'narrative',
    'source' => null,
    'status' => 'Siap disalin',
    'textareaId' => null,
])

@php
    $fieldId = $textareaId ?: 'academic-output-'.\Illuminate\Support\Str::uuid();
@endphp

<section {{ $attributes->merge(['class' => 'rounded-lg border border-blue-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Academic Output</p>
            <h2 class="mt-1 text-lg font-semibold text-slate-950">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p>
            @endif
            @if ($source)
                <p class="mt-2 text-xs font-semibold text-slate-500">{{ $source }}</p>
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $status }}</span>
            <button
                type="button"
                onclick="navigator.clipboard?.writeText(document.getElementById('{{ $fieldId }}').value); this.textContent = 'Tersalin';"
                class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 shadow-sm hover:bg-blue-100"
            >
                Salin Narasi
            </button>
        </div>
    </div>
    <textarea id="{{ $fieldId }}" readonly rows="6" class="mt-4 block w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-800 shadow-sm">{{ $narrative }}</textarea>
</section>
