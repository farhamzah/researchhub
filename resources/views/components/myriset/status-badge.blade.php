@props([
    'status',
    'label' => null,
    'size' => 'sm',
])

@php
    $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));
    $display = $label ?: str($status ?: 'not_set')->replace('_', ' ')->title()->toString();
    $sizeClass = $size === 'xs' ? 'px-2.5 py-0.5 text-xs' : 'px-3 py-1 text-sm';
    $class = match ($normalized) {
        'approved', 'completed', 'submitted', 'active', 'connected', 'ready', 'healthy' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'in_progress', 'under_review', 'published', 'open' => 'border-blue-200 bg-blue-50 text-blue-800',
        'revision_needed', 'needs_attention', 'pending', 'expired', 'token_expired', 'credentials_missing', 'partially_created' => 'border-amber-200 bg-amber-50 text-amber-900',
        'revoked', 'disabled', 'failed', 'connection_failed', 'blocked', 'void' => 'border-red-200 bg-red-50 text-red-800',
        'draft', 'closed', 'archived', 'not_started', 'disconnected' => 'border-slate-200 bg-slate-50 text-slate-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
@endphp

<span
    data-ui="myriset-status-badge"
    data-status="{{ $normalized }}"
    {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border font-semibold {$sizeClass} {$class}"]) }}
>
    {{ $display }}
</span>
