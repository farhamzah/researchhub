@php
    $optionTypes = [
        \App\Models\SurveyQuestion::TYPE_SINGLE_CHOICE,
        \App\Models\SurveyQuestion::TYPE_MULTIPLE_CHOICE,
        \App\Models\SurveyQuestion::TYPE_LIKERT,
        \App\Models\SurveyQuestion::TYPE_LIKERT_MATRIX,
    ];
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey Builder - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">ResearchHub Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Survey Builder</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $survey->title }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($survey->canReceiveResponses())
                    <a href="{{ route('survey.show', ['survey' => $survey->slug]) }}" target="_blank" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        Preview Public Form
                    </a>
                @endif
                <a href="{{ route('admin.surveys.responses.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Responses
                </a>
                <a href="{{ route('admin.surveys.scoring.index', ['survey' => $survey]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Scoring
                </a>
                <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Surveys
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
                <p class="font-semibold">Please check the builder form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-2 text-lg font-semibold">{{ str_replace('_', ' ', $survey->status) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Responses</p>
                <p class="mt-2 text-lg font-semibold">{{ $survey->responses_count }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pages</p>
                <p class="mt-2 text-lg font-semibold">{{ $survey->pages->count() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Questions</p>
                <p class="mt-2 text-lg font-semibold">{{ $survey->questions->count() }}</p>
            </div>
        </section>

        @if ($hasResponses)
            <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                Responses already exist. Question keys, question types, page deletion, and question deletion are locked to preserve stored answer integrity.
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Create Page</h2>
                <form method="POST" action="{{ route('admin.surveys.builder.pages.store', ['survey' => $survey]) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="page_title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input id="page_title" name="title" value="{{ old('title') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="page_description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="page_description" name="description" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label for="page_sort_order" class="block text-sm font-medium text-gray-700">Sort order</label>
                        <input id="page_sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                        Add Page
                    </button>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Create Question</h2>
                <form method="POST" action="{{ route('admin.surveys.builder.questions.store', ['survey' => $survey]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label for="question_page_id" class="block text-sm font-medium text-gray-700">Page optional</label>
                        <select id="question_page_id" name="page_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <option value="">No page</option>
                            @foreach ($survey->pages as $page)
                                <option value="{{ $page->id }}" @selected(old('page_id') === $page->id)>{{ $page->title ?: 'Untitled page' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="question_sort_order" class="block text-sm font-medium text-gray-700">Sort order</label>
                        <input id="question_sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="question_key" class="block text-sm font-medium text-gray-700">Question key optional</label>
                        <input id="question_key" name="question_key" value="{{ old('question_key') }}" placeholder="auto_generated_from_label" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="question_type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="question_type" name="type" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            @foreach ($questionTypes as $type)
                                <option value="{{ $type }}" @selected(old('type', \App\Models\SurveyQuestion::TYPE_SHORT_TEXT) === $type)>{{ str_replace('_', ' ', $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="question_label" class="block text-sm font-medium text-gray-700">Label</label>
                        <input id="question_label" name="label" required value="{{ old('label') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label for="question_help_text" class="block text-sm font-medium text-gray-700">Help text</label>
                        <textarea id="question_help_text" name="help_text" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('help_text') }}</textarea>
                    </div>
                    <div>
                        <label for="question_options_json" class="block text-sm font-medium text-gray-700">Options JSON</label>
                        <textarea id="question_options_json" name="options_json" rows="5" placeholder='{"choices":["Option A","Option B"]}' class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm">{{ old('options_json') }}</textarea>
                    </div>
                    <div>
                        <label for="question_settings_json" class="block text-sm font-medium text-gray-700">Settings JSON</label>
                        <textarea id="question_settings_json" name="settings_json" rows="5" placeholder='{"scale":[1,2,3,4,5]}' class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm">{{ old('settings_json') }}</textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                        <input type="hidden" name="is_required" value="0">
                        <input type="checkbox" name="is_required" value="1" @checked(old('is_required')) class="rounded border-gray-300 text-emerald-700">
                        Required question
                    </label>
                    <button type="submit" class="md:col-span-2 w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                        Add Question
                    </button>
                </form>
            </section>
        </div>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Pages</h2>
            <div class="mt-5 space-y-4">
                @forelse ($survey->pages as $page)
                    <form method="POST" action="{{ route('admin.surveys.builder.pages.update', ['survey' => $survey, 'page' => $page]) }}" class="grid gap-3 rounded-md border border-gray-200 p-4 md:grid-cols-[1fr_1fr_120px_auto_auto]">
                        @csrf
                        @method('PUT')
                        <input name="title" value="{{ $page->title }}" placeholder="Untitled page" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <input name="description" value="{{ $page->description }}" placeholder="Description" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <input name="sort_order" type="number" min="0" value="{{ $page->sort_order }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">Save</button>
                        @if (! $hasResponses)
                            <button form="delete-page-{{ $page->id }}" type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                        @else
                            <span class="self-center text-xs text-gray-500">Delete locked</span>
                        @endif
                    </form>
                    <form id="delete-page-{{ $page->id }}" method="POST" action="{{ route('admin.surveys.builder.pages.delete', ['survey' => $survey, 'page' => $page]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                @empty
                    <p class="rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">No pages yet.</p>
                @endforelse
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Questions</h2>
            <div class="mt-5 space-y-5">
                @forelse ($survey->questions as $question)
                    <form method="POST" action="{{ route('admin.surveys.builder.questions.update', ['survey' => $survey, 'question' => $question]) }}" class="rounded-md border border-gray-200 p-4">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Page</label>
                                <select name="page_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                    <option value="">No page</option>
                                    @foreach ($survey->pages as $page)
                                        <option value="{{ $page->id }}" @selected($question->page_id === $page->id)>{{ $page->title ?: 'Untitled page' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Key</label>
                                <input name="question_key" value="{{ $question->question_key }}" @readonly($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm @if($hasResponses) bg-gray-100 @endif">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Type</label>
                                <select name="type" @disabled($hasResponses) class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm @if($hasResponses) bg-gray-100 @endif">
                                    @foreach ($questionTypes as $type)
                                        <option value="{{ $type }}" @selected($question->type === $type)>{{ str_replace('_', ' ', $type) }}</option>
                                    @endforeach
                                </select>
                                @if ($hasResponses)
                                    <input type="hidden" name="type" value="{{ $question->type }}">
                                @endif
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Sort</label>
                                <input name="sort_order" type="number" min="0" value="{{ $question->sort_order }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            </div>
                            <div class="lg:col-span-4">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Label</label>
                                <input name="label" value="{{ $question->label }}" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            </div>
                            <div class="lg:col-span-4">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Help text</label>
                                <textarea name="help_text" rows="2" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $question->help_text }}</textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Options JSON</label>
                                <textarea name="options_json" rows="5" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm">{{ $json($question->options) }}</textarea>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Settings JSON</label>
                                <textarea name="settings_json" rows="5" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs shadow-sm">{{ $json($question->settings) }}</textarea>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="is_required" value="0">
                                <input type="checkbox" name="is_required" value="1" @checked($question->is_required) class="rounded border-gray-300 text-emerald-700">
                                Required
                            </label>
                            <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">Save</button>
                            <button form="duplicate-question-{{ $question->id }}" type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Duplicate</button>
                            @if (! $hasResponses)
                                <button form="delete-question-{{ $question->id }}" type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Delete</button>
                            @else
                                <span class="self-center text-xs text-gray-500">Delete locked</span>
                            @endif
                        </div>
                    </form>
                    <form id="duplicate-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.duplicate', ['survey' => $survey, 'question' => $question]) }}">
                        @csrf
                    </form>
                    <form id="delete-question-{{ $question->id }}" method="POST" action="{{ route('admin.surveys.builder.questions.delete', ['survey' => $survey, 'question' => $question]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                @empty
                    <p class="rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">No questions yet.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
