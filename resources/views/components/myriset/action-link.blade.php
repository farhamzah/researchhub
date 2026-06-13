@props([
    'href',
    'variant' => 'secondary',
])

@php
    $classes = match ($variant) {
        'primary' => 'border-transparent bg-blue-700 text-white hover:bg-blue-600 focus-visible:outline-blue-700',
        'success' => 'border-transparent bg-emerald-700 text-white hover:bg-emerald-600 focus-visible:outline-emerald-700',
        'danger' => 'border-transparent bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600',
        default => 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:outline-blue-700',
    };
@endphp

<a
    href="{{ $href }}"
    data-ui="myriset-action-link"
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-semibold shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 {$classes}"]) }}
>
    {{ $slot }}
</a>
