<article class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-4" data-review-item>
    <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500">
        <span class="rounded bg-slate-900 px-2 py-1 text-white">{{ $question['question_key'] ?: 'Tanpa kode' }}</span>
        <span>{{ str($question['type'])->replace('_', ' ') }}</span>
        <span>{{ $question['is_required'] ? 'Wajib' : 'Opsional' }}</span>
    </div>
    <p class="mt-3 break-words font-semibold leading-6">{{ $question['label'] }}</p>
    @if ($choiceLabels($question))<p class="mt-2 break-words text-sm leading-6 text-slate-600"><span class="font-semibold">Seluruh opsi:</span> {{ $choiceLabels($question) }}</p>@endif
    @if (filled(data_get($question, 'settings.interviewer_probe')))
        <aside class="mt-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-950"><span class="font-semibold">Probe pewawancara:</span> {{ data_get($question, 'settings.interviewer_probe') }}</aside>
    @endif
    <details class="mt-3 rounded-lg border border-slate-200 bg-white p-3">
        <summary class="cursor-pointer text-sm font-semibold">Detail teknis item</summary>
        <pre class="mt-3 max-w-full overflow-x-auto whitespace-pre-wrap break-words text-xs text-slate-600">{{ json_encode(['tipe' => $question['type'], 'settings' => $question['settings'] ?? [], 'scoring' => $question['scoring'] ?? null], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
    <div class="mt-4 grid min-w-0 gap-3 md:grid-cols-2">
        <input type="hidden" name="comments[item_{{ $question['id'] }}][comment_type]" value="{{ \App\Models\SurveySupervisorReviewComment::TYPE_ITEM }}">
        <input type="hidden" name="comments[item_{{ $question['id'] }}][survey_question_id]" value="{{ $question['id'] }}">
        <input type="hidden" name="comments[item_{{ $question['id'] }}][target_key]" value="{{ $question['question_key'] }}">
        <input type="hidden" name="comments[item_{{ $question['id'] }}][target_label]" value="{{ $question['label'] }}">
        <label class="min-w-0"><span class="text-sm font-semibold">Keputusan item</span><select required data-item-decision name="comments[item_{{ $question['id'] }}][decision]" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300"><option value="">Pilih keputusan</option>@foreach ($itemDecisions as $value => $label)<option value="{{ $value }}" @selected(old("comments.item_{$question['id']}.decision") === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="min-w-0"><span class="text-sm font-semibold">Tingkat perhatian</span><select name="comments[item_{{ $question['id'] }}][severity]" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300"><option value="">Tidak ditentukan</option>@foreach ($severities as $severity)<option value="{{ $severity }}">{{ str($severity)->title() }}</option>@endforeach</select></label>
        <label class="min-w-0 md:col-span-2"><span class="text-sm font-semibold">Komentar per item</span><textarea name="comments[item_{{ $question['id'] }}][comment]" rows="2" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300">{{ old("comments.item_{$question['id']}.comment") }}</textarea></label>
        <label class="min-w-0 md:col-span-2"><span class="text-sm font-semibold">Usulan redaksi reviewer</span><textarea name="comments[item_{{ $question['id'] }}][suggested_revision]" rows="2" class="mt-1 block w-full min-w-0 rounded-lg border-slate-300">{{ old("comments.item_{$question['id']}.suggested_revision") }}</textarea></label>
    </div>
</article>
