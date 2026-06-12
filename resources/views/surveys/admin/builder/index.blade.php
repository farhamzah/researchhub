@php
    use App\Models\Survey;
    use App\Models\SurveyQuestion;

    $label = fn (?string $value): string => str($value ?: 'not_set')->replace('_', ' ')->title()->toString();
    $statusClass = match ($survey->status) {
        Survey::STATUS_PUBLISHED => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        Survey::STATUS_CLOSED => 'border-amber-200 bg-amber-50 text-amber-800',
        Survey::STATUS_ARCHIVED => 'border-slate-200 bg-slate-100 text-slate-700',
        default => 'border-blue-200 bg-blue-50 text-blue-700',
    };
    $identityClass = match ($survey->identity_mode) {
        Survey::IDENTITY_FULL => 'border-red-200 bg-red-50 text-red-700',
        Survey::IDENTITY_ANONYMOUS => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        Survey::IDENTITY_PSEUDONYM => 'border-blue-200 bg-blue-50 text-blue-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
    $typeClass = fn (string $type): string => match ($type) {
        SurveyQuestion::TYPE_SINGLE_CHOICE, SurveyQuestion::TYPE_MULTIPLE_CHOICE => 'border-blue-200 bg-blue-50 text-blue-700',
        SurveyQuestion::TYPE_LIKERT, SurveyQuestion::TYPE_LIKERT_MATRIX => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        SurveyQuestion::TYPE_NUMBER, SurveyQuestion::TYPE_DATE => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        SurveyQuestion::TYPE_CONSENT => 'border-amber-200 bg-amber-50 text-amber-800',
        SurveyQuestion::TYPE_HIDDEN => 'border-red-200 bg-red-50 text-red-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
    $choices = function (?SurveyQuestion $question, int $slots = 6): array {
        $options = $question?->options ?? [];
        $values = array_is_list($options) ? $options : ($options['choices'] ?? $options['options'] ?? []);
        $values = array_values(array_map(fn ($choice): string => is_array($choice)
            ? (string) ($choice['label'] ?? $choice['value'] ?? '')
            : (string) $choice, is_array($values) ? $values : []));

        return array_pad(array_slice($values, 0, $slots), $slots, '');
    };
    $scale = function (?SurveyQuestion $question): array {
        $options = $question?->options ?? [];
        $settings = $question?->settings ?? [];
        $values = $settings['scale'] ?? $options['scale'] ?? config('researchhub_surveys.default_likert_scale', [1, 2, 3, 4, 5]);

        return array_pad(array_slice(array_map('strval', is_array($values) ? $values : []), 0, 5), 5, '');
    };
    $matrixRows = function (?SurveyQuestion $question): array {
        $rows = $question?->options['rows'] ?? [];

        return array_pad(array_slice(array_map('strval', is_array($rows) ? $rows : []), 0, 4), 4, '');
    };
    $matrixColumns = function (?SurveyQuestion $question): array {
        $options = $question?->options ?? [];
        $settings = $question?->settings ?? [];
        $columns = $options['columns'] ?? $settings['scale'] ?? config('researchhub_surveys.default_likert_scale', [1, 2, 3, 4, 5]);

        return array_pad(array_slice(array_map('strval', is_array($columns) ? $columns : []), 0, 5), 5, '');
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Builder - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">ResearchHub Admin</p>
                    <h1 class="mt-2 text-3xl font-semibold">Survey Builder</h1>
                    <p class="mt-2 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Build and review the survey instrument for {{ $survey->project?->title ?: 'No project assigned' }}.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($survey->canReceiveResponses())
                        <a href="{{ route('survey.show', ['survey' => $survey->slug]) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Preview Public Form
                        </a>
                    @else
                        <span class="rounded-md border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500">
                            Preview available after publish
                        </span>
                    @endif
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Back to Surveys
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $survey->project?->title ?: 'Not assigned' }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                    <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-semibold {{ $statusClass }}">{{ $label($survey->status) }}</span>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Identity Mode</p>
                    <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-semibold {{ $identityClass }}">{{ $label($survey->identity_mode) }}</span>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responses</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $survey->responses_count }}</p>
                </div>
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Questions</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950">{{ $survey->questions->count() }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                Identity mode controls whether respondent identity is collected or anonymized. Do not add sensitive personal data questions unless required by protocol and ethics approval.
            </div>
        </section>

        @if (session('status'))
            <section class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                {{ str_replace('-', ' ', session('status')) }}
            </section>
        @endif

        @if ($errors->any())
            <section class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please check the builder form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($hasResponses)
            <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                This survey already has responses. Question keys, question types, options, settings, page deletion, and question deletion are locked to preserve stored answer integrity.
            </section>
        @endif

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap gap-2">
                <a href="#add-question" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                    Add Question
                </a>
                <a href="#question-list" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Review Questions
                </a>
                <a href="{{ route('admin.surveys.responses.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Open Responses
                </a>
                <a href="{{ route('admin.surveys.analysis.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Open Analysis
                </a>
                <a href="{{ route('admin.surveys.scoring.index', ['survey' => $survey]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Open Scoring
                </a>
            </div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[0.42fr_0.58fr]">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Pages</h2>
                <p class="mt-1 text-sm text-slate-600">Optional sections help group long instruments.</p>

                <form method="POST" action="{{ route('admin.surveys.builder.pages.store', ['survey' => $survey]) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="page_title" class="block text-sm font-medium text-slate-700">Page title</label>
                        <input id="page_title" name="title" value="{{ old('title') }}" placeholder="Section A: Respondent profile" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="page_description" class="block text-sm font-medium text-slate-700">Description</label>
                        <textarea id="page_description" name="description" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label for="page_sort_order" class="block text-sm font-medium text-slate-700">Order</label>
                        <input id="page_sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $survey->pages->count() + 1) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Add Page
                    </button>
                </form>

                <div class="mt-6 space-y-3">
                    @forelse ($survey->pages as $page)
                        <form method="POST" action="{{ route('admin.surveys.builder.pages.update', ['survey' => $survey, 'page' => $page]) }}" class="rounded-md border border-slate-200 bg-slate-50 p-4">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-3">
                                <input name="title" value="{{ $page->title }}" placeholder="Untitled page" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                <input name="description" value="{{ $page->description }}" placeholder="Description" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                                <input name="sort_order" type="number" min="0" value="{{ $page->sort_order }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save Page</button>
                                @if (! $hasResponses)
                                    <button form="delete-page-{{ $page->id }}" type="submit" onclick="return confirm('Delete this survey page? Questions remain in the survey unless moved separately.')" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                                @else
                                    <span class="self-center text-xs font-semibold text-slate-500">Delete locked</span>
                                @endif
                            </div>
                        </form>
                        <form id="delete-page-{{ $page->id }}" method="POST" action="{{ route('admin.surveys.builder.pages.delete', ['survey' => $survey, 'page' => $page]) }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    @empty
                        <p class="rounded-md border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">No pages yet. Questions can still be created without a page.</p>
                    @endforelse
                </div>
            </section>

            <section id="add-question" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Add Question</h2>
                <p class="mt-1 text-sm text-slate-600">Use structured choice fields for common research instrument questions. Advanced JSON remains available for edge cases.</p>

                <form method="POST" action="{{ route('admin.surveys.builder.questions.store', ['survey' => $survey]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    @include('surveys.admin.builder.partials.question-form', [
                        'question' => null,
                        'survey' => $survey,
                        'questionTypes' => $questionTypes,
                        'hasResponses' => false,
                        'json' => $json,
                        'choices' => $choices,
                        'scale' => $scale,
                        'matrixRows' => $matrixRows,
                        'matrixColumns' => $matrixColumns,
                    ])
                    <button type="submit" class="md:col-span-2 w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                        Add Question
                    </button>
                </form>
            </section>
        </div>

        <section id="question-list" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Question List</h2>
                    <p class="mt-1 text-sm text-slate-600">Questions are displayed in the order respondents will see them.</p>
                </div>
                <p class="text-sm font-semibold text-slate-600">{{ $survey->questions->count() }} questions</p>
            </div>

            <div class="mt-5 space-y-5">
                @forelse ($survey->questions as $question)
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Question {{ $loop->iteration }} / Order {{ $question->sort_order }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ $question->label }}</h3>
                                @if ($question->help_text)
                                    <p class="mt-1 text-sm text-slate-600">{{ $question->help_text }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $typeClass($question->type) }}">{{ $label($question->type) }}</span>
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $question->is_required ? 'border-red-200 bg-red-50 text-red-700' : 'border-slate-200 bg-slate-50 text-slate-700' }}">{{ $question->is_required ? 'Required' : 'Optional' }}</span>
                                @if ($question->scoring?->indicator)
                                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Indicator: {{ $question->scoring->indicator->name }}</span>
                                @else
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">No indicator</span>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                            @csrf
                            @method('PUT')
                            @include('surveys.admin.builder.partials.question-form', [
                                'question' => $question,
                                'survey' => $survey,
                                'questionTypes' => $questionTypes,
                                'hasResponses' => $hasResponses,
                                'json' => $json,
                                'choices' => $choices,
                                'scale' => $scale,
                                'matrixRows' => $matrixRows,
                                'matrixColumns' => $matrixColumns,
                            ])
                            <div class="md:col-span-2 flex flex-wrap gap-2">
                                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save Question</button>
                                <button form="move-up-question-{{ $question->id }}" type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Move Up</button>
                                <button form="move-down-question-{{ $question->id }}" type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Move Down</button>
                                <button form="duplicate-question-{{ $question->id }}" type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Duplicate</button>
                                @if (! $hasResponses)
                                    <button form="delete-question-{{ $question->id }}" type="submit" onclick="return confirm('Delete this question? This cannot be undone.')" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                                @else
                                    <span class="self-center text-xs font-semibold text-slate-500">Delete locked</span>
                                @endif
                            </div>
                        </form>

                        @foreach (['up' => max(0, $question->sort_order - 1), 'down' => $question->sort_order + 1] as $direction => $sortOrder)
                            <form id="move-{{ $direction }}-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="page_id" value="{{ $question->page_id }}">
                                <input type="hidden" name="question_key" value="{{ $question->question_key }}">
                                <input type="hidden" name="type" value="{{ $question->type }}">
                                <input type="hidden" name="label" value="{{ $question->label }}">
                                <input type="hidden" name="help_text" value="{{ $question->help_text }}">
                                <input type="hidden" name="options_json" value="{{ $json($question->options) }}">
                                <input type="hidden" name="settings_json" value="{{ $json($question->settings) }}">
                                <input type="hidden" name="is_required" value="{{ $question->is_required ? 1 : 0 }}">
                                <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                            </form>
                        @endforeach

                        <form id="duplicate-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.duplicate', ['survey' => $survey, 'question' => $question]) }}">
                            @csrf
                        </form>
                        <form id="delete-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.delete', ['survey' => $survey, 'question' => $question]) }}">
                            @csrf
                            @method('DELETE')
                        </form>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
                        <h3 class="text-lg font-semibold text-slate-950">No questions yet</h3>
                        <p class="mt-2 text-sm text-slate-600">Start with a short text, single choice, or Likert question to build your instrument.</p>
                        <a href="#add-question" class="mt-4 inline-flex rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Create Question</a>
                    </div>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
