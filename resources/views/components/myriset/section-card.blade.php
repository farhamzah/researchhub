@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
])

<section
    data-ui="myriset-section-card"
    {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6']) }}
>
    @if ($eyebrow || $title || $description || isset($actions))
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @if ($eyebrow)
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">{{ $eyebrow }}</p>
                @endif
                @if ($title)
                    <h2 class="mt-1 text-xl font-semibold text-slate-950">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex flex-wrap gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
