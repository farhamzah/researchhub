@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<header
    data-ui="myriset-page-header"
    {{ $attributes->merge(['class' => 'mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm']) }}
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-3xl">
            @if ($eyebrow)
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">{{ $eyebrow }}</p>
            @endif

            <h1 class="mt-2 text-3xl font-semibold text-slate-950">{{ $title }}</h1>

            @if ($description)
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
            @endif

            {{ $slot }}
        </div>

        @isset($actions)
            <div class="flex flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
</header>
