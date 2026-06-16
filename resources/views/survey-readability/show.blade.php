@extends('layouts.public-review')

@section('title', $round->title.' - MyRiset Readability Test')

@section('content')
    <section data-ui="myriset-page-header" class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Readability Test</p>
                <h1 class="mt-2 text-3xl font-semibold">Uji Keterbacaan Instrumen</h1>
                <p class="mt-3 text-lg font-semibold text-slate-800">{{ $survey->title }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $project?->title ?? 'Research project' }}</p>
            </div>
            <x-myriset.status-badge status="active" label="Pilot review link" />
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Readability Round</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $round->title }}</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Participant</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $participant->participant_name ?: 'Anonymous allowed' }}</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Questions</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ $questions->count() }} items to review</p>
            </div>
            <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">Readability only</p>
            </div>
        </div>
    </section>

    <section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-950">
        <h2 class="text-lg font-semibold">Petunjuk uji keterbacaan</h2>
        <p class="mt-2">Mohon menilai apakah instrumen mudah dipahami, tidak ambigu, tidak terlalu panjang, dan sesuai untuk responden sasaran. Jawaban di halaman ini tidak dihitung sebagai respons survei utama.</p>
        @if ($round->instructions)
            <p class="mt-4 rounded-md border border-blue-200 bg-white/70 p-3">{{ $round->instructions }}</p>
        @endif
    </section>

    @if ($errors->any())
        <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-5 text-sm leading-6 text-red-950" role="alert">
            <h2 class="text-lg font-semibold">Mohon lengkapi uji keterbacaan.</h2>
            <ul class="mt-3 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($questions->isEmpty())
        <x-myriset.empty-state
            class="bg-white shadow-sm"
            title="This survey has no questions yet."
            description="Readability feedback cannot be submitted until the researcher adds survey questions."
        />
    @else
        <form method="POST" action="{{ route('readability.survey.store', ['token' => $token]) }}" class="space-y-6">
            @csrf

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Participant Identity</p>
                <h2 class="mt-1 text-lg font-semibold leading-7 text-slate-950">Identitas peserta pilot</h2>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <div>
                        <label for="participant_name" class="block text-sm font-semibold text-slate-700">Name optional</label>
                        <input id="participant_name" name="participant_name" value="{{ old('participant_name', $participant->participant_name) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label for="participant_type" class="block text-sm font-semibold text-slate-700">Participant type</label>
                        <select id="participant_type" name="participant_type" class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                            <option value="">Prefer not to say</option>
                            @foreach ($participantTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('participant_type', $participant->participant_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="institution" class="block text-sm font-semibold text-slate-700">Institution optional</label>
                        <input id="institution" name="institution" value="{{ old('institution', $participant->institution) }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base shadow-sm sm:text-sm">
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Questionnaire Preview</p>
                <h2 class="mt-1 text-lg font-semibold leading-7 text-slate-950">Butir instrumen yang ditinjau</h2>
                <div class="mt-5 space-y-4">
                    @foreach ($questions as $question)
                        <article class="rounded-md border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Question {{ $loop->iteration }}</p>
                            <p class="mt-1 font-semibold text-slate-950">{{ $question->label }}</p>
                            @if ($question->help_text)
                                <p class="mt-1 text-sm text-slate-600">{{ $question->help_text }}</p>
                            @endif
                            <p class="mt-2 text-xs font-semibold text-slate-500">{{ str_replace('_', ' ', $question->type) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Overall Assessment</p>
                <h2 class="mt-1 text-lg font-semibold leading-7 text-slate-950">Skala 1-5</h2>
                <p class="mt-2 text-sm text-slate-600">1 = very unclear, 2 = unclear, 3 = fairly clear, 4 = clear, 5 = very clear.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'instruction_clarity_score' => 'Instructions are clear.',
                        'overall_clarity_score' => 'Questions are easy to understand.',
                        'terminology_clarity_score' => 'Terms are understandable.',
                        'answer_option_clarity_score' => 'Answer options are clear.',
                        'overall_length_score' => 'The questionnaire is not too long.',
                    ] as $field => $label)
                        <div>
                            <label for="{{ $field }}" class="block text-sm font-semibold text-slate-700">{{ $label }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" required class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                                <option value="">Select score</option>
                                @for ($score = 1; $score <= 5; $score++)
                                    <option value="{{ $score }}" @selected(old($field) == $score)>{{ $score }}</option>
                                @endfor
                            </select>
                        </div>
                    @endforeach
                    <div>
                        <label for="estimated_completion_minutes" class="block text-sm font-semibold text-slate-700">Estimated completion time in minutes</label>
                        <input id="estimated_completion_minutes" name="estimated_completion_minutes" type="number" min="1" max="600" value="{{ old('estimated_completion_minutes') }}" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base shadow-sm sm:text-sm">
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Confusing Items</p>
                <h2 class="mt-1 text-lg font-semibold leading-7 text-slate-950">Butir yang perlu diperjelas</h2>
                <p class="mt-2 text-sm text-slate-600">Isi baris yang relevan saja. Kosongkan jika tidak ada butir yang membingungkan.</p>
                <div class="mt-5 space-y-4">
                    @for ($index = 0; $index < 5; $index++)
                        <div class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 lg:grid-cols-[0.9fr_0.9fr_1.4fr]">
                            <div>
                                <label for="feedback_question_{{ $index }}" class="block text-sm font-semibold text-slate-700">Question number</label>
                                <select id="feedback_question_{{ $index }}" name="feedback[{{ $index }}][survey_question_id]" class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                                    <option value="">Select question</option>
                                    @foreach ($questions as $question)
                                        <option value="{{ $question->id }}" @selected(old("feedback.{$index}.survey_question_id") === $question->id)>Question {{ $loop->iteration }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="feedback_issue_{{ $index }}" class="block text-sm font-semibold text-slate-700">Issue type</label>
                                <select id="feedback_issue_{{ $index }}" name="feedback[{{ $index }}][issue_type]" class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                                    <option value="">Select issue</option>
                                    @foreach ($issueTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old("feedback.{$index}.issue_type") === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="feedback_comment_{{ $index }}" class="block text-sm font-semibold text-slate-700">Comment</label>
                                <textarea id="feedback_comment_{{ $index }}" name="feedback[{{ $index }}][comment]" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old("feedback.{$index}.comment") }}</textarea>
                            </div>
                        </div>
                    @endfor
                </div>
                <div class="mt-4">
                    <label for="confusing_items" class="block text-sm font-semibold text-slate-700">Other confusing items</label>
                    <textarea id="confusing_items" name="confusing_items" rows="3" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('confusing_items') }}</textarea>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Final Decision</p>
                <h2 class="mt-1 text-lg font-semibold leading-7 text-slate-950">Kesimpulan uji keterbacaan</h2>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <div>
                        <label for="final_decision" class="block text-sm font-semibold text-slate-700">Final decision</label>
                        <select id="final_decision" name="final_decision" required class="mt-2 block min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base shadow-sm sm:text-sm">
                            <option value="">Select decision</option>
                            @foreach ($finalDecisions as $value => $label)
                                <option value="{{ $value }}" @selected(old('final_decision') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="general_comments" class="block text-sm font-semibold text-slate-700">General suggestions</label>
                        <textarea id="general_comments" name="general_comments" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('general_comments') }}</textarea>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="revision_suggestions" class="block text-sm font-semibold text-slate-700">Revision suggestions</label>
                        <textarea id="revision_suggestions" name="revision_suggestions" rows="4" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-base leading-6 shadow-sm sm:text-sm">{{ old('revision_suggestions') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-emerald-200 bg-white p-5 shadow-sm">
                <p class="text-sm leading-6 text-slate-600">Setelah dikirim, isian tidak dapat diubah melalui link ini kecuali peneliti membuka ulang link. Data ini hanya untuk uji keterbacaan dan tidak menjadi respons survei utama.</p>
                <button type="submit" class="mt-4 w-full rounded-md bg-emerald-700 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-emerald-600">
                    Submit Readability Feedback
                </button>
            </section>
        </form>
    @endif
@endsection
