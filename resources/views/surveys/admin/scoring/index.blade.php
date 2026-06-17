@php
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
    $scoringStatus = function ($question) use ($supportedTypes): string {
        $scoring = $question->scoring;

        if (in_array($question->type, [
            \App\Models\SurveyQuestion::TYPE_SHORT_TEXT,
            \App\Models\SurveyQuestion::TYPE_LONG_TEXT,
            \App\Models\SurveyQuestion::TYPE_DATE,
            \App\Models\SurveyQuestion::TYPE_CONSENT,
            \App\Models\SurveyQuestion::TYPE_HIDDEN,
            \App\Models\SurveyQuestion::TYPE_LIKERT_MATRIX,
        ], true)) {
            return 'Not scoreable';
        }

        if (! in_array($question->type, $supportedTypes, true)) {
            return 'Not scoreable';
        }

        if (! $scoring || ! $scoring->is_scored) {
            return 'Descriptive';
        }

        if (! $scoring->indicator) {
            return 'Missing indicator';
        }

        if ($scoring->indicator->scale && ($scoring->score_min === null || $scoring->score_max === null)) {
            return 'Missing scale/range';
        }

        return 'Configured';
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Scoring - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Survey Scoring</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey->title }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.surveys.builder.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Builder
                </a>
                <a href="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Analysis
                </a>
            </div>
        </div>

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please check the scoring form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($hasResponses)
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                Submitted responses already exist. Scoring configuration is locked until versioned scoring configuration is implemented.
            </section>
        @endif

        <section class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Scales</p>
                <p class="mt-2 text-lg font-semibold">{{ $survey->scales->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Indicators</p>
                <p class="mt-2 text-lg font-semibold">{{ $survey->indicators->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Questions</p>
                <p class="mt-2 text-lg font-semibold">{{ $survey->questions->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Responses</p>
                <p class="mt-2 text-lg font-semibold">{{ $survey->responses_count }}</p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Create Scale</h2>
                <form method="POST" action="{{ route('admin.surveys.scoring.scales.store', ['survey' => $survey]) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="scale_name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input id="scale_name" name="name" required value="{{ old('name') }}" @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="scale_description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="scale_description" name="description" rows="3" @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label for="scale_sort_order" class="block text-sm font-medium text-gray-700">Sort order</label>
                        <input id="scale_sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <button type="submit" @disabled($hasResponses) class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600 disabled:bg-gray-300">
                        Add Scale
                    </button>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Create Indicator</h2>
                <form method="POST" action="{{ route('admin.surveys.scoring.indicators.store', ['survey' => $survey]) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="indicator_scale_id" class="block text-sm font-medium text-gray-700">Scale optional</label>
                        <select id="indicator_scale_id" name="survey_scale_id" @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <option value="">No scale</option>
                            @foreach ($survey->scales as $scale)
                                <option value="{{ $scale->id }}">{{ $scale->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="indicator_name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input id="indicator_name" name="name" required @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="indicator_description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="indicator_description" name="description" rows="3" @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label for="indicator_rules" class="block text-sm font-medium text-gray-700">Interpretation rules JSON optional</label>
                        <textarea id="indicator_rules" name="interpretation_rules_json" rows="5" placeholder='[{"min":1,"max":1.8,"label":"Sangat rendah"}]' @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm"></textarea>
                    </div>
                    <button type="submit" @disabled($hasResponses) class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600 disabled:bg-gray-300">
                        Add Indicator
                    </button>
                </form>
            </section>
        </div>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Scales and Indicators</h2>
            <div class="mt-5 space-y-4">
                @forelse ($survey->scales as $scale)
                    <form method="POST" action="{{ route('admin.surveys.scoring.scales.update', ['survey' => $survey, 'scale' => $scale]) }}" class="grid gap-3 rounded-md border border-gray-200 p-4 md:grid-cols-[1fr_1fr_120px_auto_auto]">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $scale->name }}" @disabled($hasResponses) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <input name="description" value="{{ $scale->description }}" @disabled($hasResponses) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <input name="sort_order" type="number" min="0" value="{{ $scale->sort_order }}" @disabled($hasResponses) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <button type="submit" @disabled($hasResponses) class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:bg-gray-300">Save</button>
                        @if (! $hasResponses)
                            <button form="delete-scale-{{ $scale->id }}" type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                        @else
                            <span class="self-center text-xs text-gray-500">Locked</span>
                        @endif
                    </form>
                    <form id="delete-scale-{{ $scale->id }}" method="POST" action="{{ route('admin.surveys.scoring.scales.delete', ['survey' => $survey, 'scale' => $scale]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                @empty
                    <p class="rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">No scales yet.</p>
                @endforelse

                @foreach ($survey->indicators as $indicator)
                    <form method="POST" action="{{ route('admin.surveys.scoring.indicators.update', ['survey' => $survey, 'indicator' => $indicator]) }}" class="grid gap-3 rounded-md border border-gray-200 p-4 md:grid-cols-[1fr_1fr_1fr_auto_auto]">
                        @csrf
                        @method('PUT')
                        <select name="survey_scale_id" @disabled($hasResponses) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <option value="">No scale</option>
                            @foreach ($survey->scales as $scale)
                                <option value="{{ $scale->id }}" @selected($indicator->survey_scale_id === $scale->id)>{{ $scale->name }}</option>
                            @endforeach
                        </select>
                        <input name="name" value="{{ $indicator->name }}" @disabled($hasResponses) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <input name="sort_order" type="number" min="0" value="{{ $indicator->sort_order }}" @disabled($hasResponses) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <button type="submit" @disabled($hasResponses) class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:bg-gray-300">Save</button>
                        @if (! $hasResponses)
                            <button form="delete-indicator-{{ $indicator->id }}" type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                        @else
                            <span class="self-center text-xs text-gray-500">Locked</span>
                        @endif
                        <textarea name="description" rows="2" placeholder="Indicator description" @disabled($hasResponses) class="md:col-span-5 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $indicator->description }}</textarea>
                        <textarea name="interpretation_rules_json" rows="4" @disabled($hasResponses) class="md:col-span-5 rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm">{{ $json($indicator->interpretation_rules) }}</textarea>
                    </form>
                    <form id="delete-indicator-{{ $indicator->id }}" method="POST" action="{{ route('admin.surveys.scoring.indicators.delete', ['survey' => $survey, 'indicator' => $indicator]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Question Scoring</h2>
            <div class="mt-5 space-y-4">
                @foreach ($survey->questions as $question)
                    @php
                        $scoring = $question->scoring;
                        $canScoreType = in_array($question->type, $supportedTypes, true);
                        $status = $scoringStatus($question);
                    @endphp
                    <form method="POST" action="{{ route('admin.surveys.scoring.questions.update', ['survey' => $survey, 'question' => $question]) }}" class="rounded-md border border-gray-200 p-4">
                        @csrf
                        @method('PUT')
                        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-semibold">{{ $question->label }}</h3>
                                <p class="text-xs text-gray-500">{{ $question->question_key }} - {{ str_replace('_', ' ', $question->type) }}</p>
                            </div>
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $status === 'Configured' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($status === 'Descriptive' || $status === 'Not scoreable' ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-amber-200 bg-amber-50 text-amber-800') }}">{{ $status }}</span>
                        </div>
                        @if ($question->type === \App\Models\SurveyQuestion::TYPE_LIKERT_MATRIX)
                            <div class="mb-3 flex flex-col gap-3 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-900 sm:flex-row sm:items-center sm:justify-between">
                                <p>Likert Matrix is collected for analysis/export but not included in scoring. Convert matrix rows to individual Likert questions if each row must contribute to indicator scoring.</p>
                                <button form="convert-matrix-{{ $question->id }}" type="submit" @disabled($hasResponses) class="shrink-0 rounded-md bg-blue-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-800 disabled:bg-slate-300">Convert Matrix</button>
                            </div>
                            <form id="convert-matrix-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.scoring.questions.convert-matrix', ['survey' => $survey, 'question' => $question]) }}">
                                @csrf
                            </form>
                        @elseif (! $canScoreType)
                            <p class="mb-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm leading-6 text-slate-600">This question type is descriptive/not scored by design and will not create scoring readiness warnings.</p>
                        @endif
                        <div class="grid gap-3 md:grid-cols-6">
                            <select name="survey_indicator_id" @disabled($hasResponses || ! $canScoreType) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm md:col-span-2">
                                <option value="">No indicator</option>
                                @foreach ($survey->indicators as $indicator)
                                    <option value="{{ $indicator->id }}" @selected($scoring?->survey_indicator_id === $indicator->id)>{{ $indicator->name }}</option>
                                @endforeach
                            </select>
                            <input name="score_min" type="number" step="0.0001" placeholder="Min" value="{{ $scoring?->score_min }}" @disabled($hasResponses || ! $canScoreType) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <input name="score_max" type="number" step="0.0001" placeholder="Max" value="{{ $scoring?->score_max }}" @disabled($hasResponses || ! $canScoreType) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <input name="weight" type="number" step="0.0001" min="0.0001" value="{{ $scoring?->weight ?? 1 }}" @disabled($hasResponses || ! $canScoreType) class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <button type="submit" @disabled($hasResponses || ! $canScoreType) class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:bg-gray-300">Save</button>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="is_scored" value="0">
                                <input type="checkbox" name="is_scored" value="1" @checked($scoring?->is_scored ?? true) @disabled($hasResponses || ! $canScoreType) class="rounded border-gray-300 text-emerald-700">
                                Scored
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="is_reverse_scored" value="0">
                                <input type="checkbox" name="is_reverse_scored" value="1" @checked($scoring?->is_reverse_scored) @disabled($hasResponses || ! $canScoreType) class="rounded border-gray-300 text-emerald-700">
                                Reverse scored
                            </label>
                            <textarea name="settings_json" rows="3" placeholder='{"scores":{"A":1,"B":2}}' @disabled($hasResponses || ! $canScoreType) class="md:col-span-4 rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm">{{ $json($scoring?->settings) }}</textarea>
                        </div>
                    </form>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
