@props([
    'title',
    'description',
    'actionUrl' => null,
    'actionLabel' => null,
])

<div
    data-ui="myriset-empty-state"
    {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center']) }}
>
    <h3 class="text-lg font-semibold text-slate-950">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $description }}</p>

    @if ($actionUrl && $actionLabel)
        <x-myriset.action-link :href="$actionUrl" variant="primary" class="mt-4">
            {{ $actionLabel }}
        </x-myriset.action-link>
    @endif

    {{ $slot }}
</div>
