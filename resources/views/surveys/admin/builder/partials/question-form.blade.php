@php
    use App\Models\SurveyQuestion;

    $questionChoices = $choices($question);
    $questionScale = $scale($question);
    $questionRows = $matrixRows($question);
    $questionColumns = $matrixColumns($question);
    $isLocked = $hasResponses && $question !== null;
@endphp

<div>
    <label class="block text-sm font-medium text-slate-700">Page optional</label>
    <select name="page_id" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
        <option value="">No page</option>
        @foreach ($survey->pages as $page)
            <option value="{{ $page->id }}" @selected(old('page_id', $question?->page_id) === $page->id)>{{ $page->title ?: 'Untitled page' }}</option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700">Order</label>
    <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $question?->sort_order ?? $survey->questions->count() + 1) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
</div>

<div>
    <label class="block text-sm font-medium text-slate-700">Question key <span class="font-normal text-slate-500">(optional)</span></label>
    <input name="question_key" value="{{ old('question_key', $question?->question_key) }}" placeholder="auto_generated_from_label" @readonly($isLocked) class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">
    <p class="mt-1 text-xs leading-5 text-slate-500">Stable export/analysis identifier. Leave blank to auto-generate. Locked after responses exist.</p>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700">Question type</label>
    <select name="type" @disabled($isLocked) class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">
        @foreach ($questionTypes as $type)
            <option value="{{ $type }}" @selected(old('type', $question?->type ?? SurveyQuestion::TYPE_SHORT_TEXT) === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>
        @endforeach
    </select>
    @if ($isLocked)
        <input type="hidden" name="type" value="{{ $question->type }}">
    @endif
</div>

<div class="md:col-span-2">
    <label class="block text-sm font-medium text-slate-700">Question text / label</label>
    <input name="label" required value="{{ old('label', $question?->label) }}" placeholder="How satisfied are you with the learning media?" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
</div>

<div class="md:col-span-2">
    <label class="block text-sm font-medium text-slate-700">Help text</label>
    <textarea name="help_text" rows="2" placeholder="Optional guidance shown below the question." class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('help_text', $question?->help_text) }}</textarea>
</div>

<div class="rounded-md border border-blue-100 bg-blue-50 p-4 md:col-span-2">
    <p class="text-sm font-semibold text-blue-900">Choice options</p>
    <p class="mt-1 text-xs leading-5 text-blue-800">Use these rows for Single Choice or Multiple Choice questions. Empty rows are ignored.</p>
    <div class="mt-3 grid gap-2 md:grid-cols-2">
        @foreach ($questionChoices as $choice)
            <input name="choice_options[]" value="{{ old("choice_options.{$loop->index}", $choice) }}" @readonly($isLocked) placeholder="Choice {{ $loop->iteration }}" class="rounded-md border border-blue-200 bg-white px-3 py-2 text-sm shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">
        @endforeach
    </div>
</div>

<div class="rounded-md border border-emerald-100 bg-emerald-50 p-4 md:col-span-2">
    <p class="text-sm font-semibold text-emerald-900">Likert scale</p>
    <p class="mt-1 text-xs leading-5 text-emerald-800">Use these values for Likert questions. Default research scale is 1 to 5.</p>
    <div class="mt-3 grid grid-cols-5 gap-2">
        @foreach ($questionScale as $scaleValue)
            <input name="likert_scale[]" value="{{ old("likert_scale.{$loop->index}", $scaleValue) }}" @readonly($isLocked) placeholder="{{ $loop->iteration }}" class="rounded-md border border-emerald-200 bg-white px-3 py-2 text-sm shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">
        @endforeach
    </div>
</div>

<details class="rounded-md border border-slate-200 bg-slate-50 md:col-span-2">
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">Advanced matrix and JSON options</summary>
    <div class="grid gap-4 border-t border-slate-200 p-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-slate-700">Matrix rows</label>
            <div class="mt-2 space-y-2">
                @foreach ($questionRows as $row)
                    <input name="matrix_rows[]" value="{{ old("matrix_rows.{$loop->index}", $row) }}" @readonly($isLocked) placeholder="Statement {{ $loop->iteration }}" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">
                @endforeach
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Matrix columns</label>
            <div class="mt-2 space-y-2">
                @foreach ($questionColumns as $column)
                    <input name="matrix_columns[]" value="{{ old("matrix_columns.{$loop->index}", $column) }}" @readonly($isLocked) placeholder="Scale {{ $loop->iteration }}" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">
                @endforeach
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Options JSON</label>
            <textarea name="options_json" rows="5" @readonly($isLocked) placeholder='{"choices":["Option A","Option B"]}' class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">{{ old('options_json', $json($question?->options)) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Settings JSON</label>
            <textarea name="settings_json" rows="5" @readonly($isLocked) placeholder='{"scale":[1,2,3,4,5]}' class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs shadow-sm @if ($isLocked) bg-slate-100 text-slate-500 @endif">{{ old('settings_json', $json($question?->settings)) }}</textarea>
        </div>
        <p class="text-xs leading-5 text-slate-500 md:col-span-2">For choice, Likert, and matrix types, the structured fields above are used first. Advanced JSON is kept for custom edge cases when structured fields are blank.</p>
    </div>
</details>

<label class="flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
    <input type="hidden" name="is_required" value="0">
    <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $question?->is_required ?? false)) class="rounded border-slate-300 text-emerald-700">
    Required question
</label>
