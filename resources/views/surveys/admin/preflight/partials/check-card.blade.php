@php
    $status = $check['status'] ?? 'info';
    $severity = $check['severity'] ?? 'info';
    $isPostFix = filled($check['fix_url'] ?? null) && str_contains((string) ($check['fix_url'] ?? ''), '/preflight/fix-student-open-questions');
@endphp

<article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass($status) }}">{{ strtoupper(str_replace('_', ' ', $status)) }}</span>
                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $severityClass($severity) }}">{{ strtoupper($severity) }}</span>
                <span class="text-xs font-mono text-slate-500">{{ $check['check_key'] ?? 'check' }}</span>
            </div>
            <h3 class="mt-2 text-base font-semibold text-slate-950">{{ $check['label'] ?? 'Checklist item' }}</h3>
            <p class="mt-1 text-sm leading-6 text-slate-700">{{ $check['message'] ?? '-' }}</p>
            @if (filled($check['recommendation'] ?? null))
                <p class="mt-2 text-sm leading-6 text-slate-600"><span class="font-semibold">Recommendation:</span> {{ $check['recommendation'] }}</p>
            @endif
        </div>

        @if (filled($check['fix_url'] ?? null))
            <div class="shrink-0">
                @if ($isPostFix)
                    <form method="POST" action="{{ $check['fix_url'] }}">
                        @csrf
                        <button type="submit" onclick="return confirm('Add only the missing Section G open-response questions? Existing questions will not be duplicated.')" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                            {{ $check['fix_action_label'] ?? 'Fix' }}
                        </button>
                    </form>
                @else
                    <a href="{{ $check['fix_url'] }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        {{ $check['fix_action_label'] ?? 'Open' }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</article>
