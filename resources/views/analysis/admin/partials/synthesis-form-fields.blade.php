@php
    use App\Models\AnalysisSynthesisItem;
@endphp

<select name="source_type" required class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
    @foreach ($synthesisOptions['sources'] as $value => $optionLabel)
        <option value="{{ $value }}" @selected(old('source_type', $item?->source_type ?? AnalysisSynthesisItem::SOURCE_MANUAL) === $value)>{{ $optionLabel }}</option>
    @endforeach
</select>
<input name="source_label" value="{{ old('source_label', $item?->source_label) }}" placeholder="Source label" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
<select name="theme" required class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
    @foreach ($synthesisOptions['themes'] as $value => $optionLabel)
        <option value="{{ $value }}" @selected(old('theme', $item?->theme ?? AnalysisSynthesisItem::THEME_OTHER) === $value)>{{ $optionLabel }}</option>
    @endforeach
</select>
<textarea name="finding" required rows="3" placeholder="Finding" class="lg:col-span-3 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('finding', $item?->finding) }}</textarea>
<textarea name="evidence_summary" rows="3" placeholder="Evidence summary" class="lg:col-span-2 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('evidence_summary', $item?->evidence_summary) }}</textarea>
<input name="evidence_metric" value="{{ old('evidence_metric', $item?->evidence_metric) }}" placeholder="Evidence metric" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
<select name="priority_level" required class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
    @foreach ($synthesisOptions['priorities'] as $value => $optionLabel)
        <option value="{{ $value }}" @selected(old('priority_level', $item?->priority_level ?? AnalysisSynthesisItem::PRIORITY_MEDIUM) === $value)>{{ $optionLabel }}</option>
    @endforeach
</select>
<select name="status" required class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
    @foreach ($synthesisOptions['statuses'] as $value => $optionLabel)
        <option value="{{ $value }}" @selected(old('status', $item?->status ?? AnalysisSynthesisItem::STATUS_PROPOSED) === $value)>{{ $optionLabel }}</option>
    @endforeach
</select>
<select name="mapped_module" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
    <option value="">Mapped module</option>
    @foreach ($synthesisOptions['modules'] as $module)
        <option value="{{ $module }}" @selected(old('mapped_module', $item?->mapped_module) === $module)>{{ $module }}</option>
    @endforeach
</select>
<textarea name="design_implication" rows="3" placeholder="Design implication" class="lg:col-span-3 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('design_implication', $item?->design_implication) }}</textarea>
<textarea name="development_decision" rows="3" placeholder="Development decision" class="lg:col-span-3 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('development_decision', $item?->development_decision) }}</textarea>
<textarea name="researcher_note" rows="2" placeholder="Researcher note" class="lg:col-span-3 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('researcher_note', $item?->researcher_note) }}</textarea>
