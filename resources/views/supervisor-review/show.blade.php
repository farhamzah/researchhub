@php
    use App\Models\SurveyQuestion;
    use App\Models\SurveySupervisorReviewComment;

    $choiceLabels = function (SurveyQuestion $question): string {
        $options = $question->options ?? [];
        $settings = $question->settings ?? [];
        $values = $options['choices'] ?? $options['options'] ?? $options['scale'] ?? $settings['scale'] ?? [];

        if ($question->type === SurveyQuestion::TYPE_LIKERT_MATRIX) {
            $rows = collect($options['rows'] ?? [])->filter()->join(', ');
            $columns = collect($options['columns'] ?? $settings['scale'] ?? [])->map(fn ($value) => is_array($value) ? ($value['label'] ?? $value['value'] ?? '') : $value)->filter()->join(', ');

            return trim('Rows: '.$rows.' | Columns: '.$columns);
        }

        return collect(is_array($values) ? $values : [])
            ->map(fn ($value) => is_array($value) ? ($value['label'] ?? $value['value'] ?? '') : $value)
            ->filter()
            ->join(', ');
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supervisor Instrument Review - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-indigo-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Supervisor Instrument Review</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $survey->title }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $round->title }} · {{ $reviewer->supervisor_name }}</p>
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-950">Supervisor review is qualitative pre-validation evidence. It is separate from expert validation scores, Aiken's V, CVI, and respondent analysis.</p>
        </section>

        <form method="POST" action="{{ route('supervisor-review.survey.store', ['token' => $token]) }}" class="mt-6 space-y-6">
            @csrf

            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">{{ $survey->intro_title ?: 'Intro Narrative' }}</h2>
                @if ($survey->intro_image_url)
                    <figure class="mt-4">
                        <img src="{{ $survey->intro_image_url }}" alt="{{ $survey->intro_image_alt_text ?: 'Survey intro illustration' }}" class="max-h-80 w-full rounded-xl border bg-slate-50 object-contain">
                        @if ($survey->intro_image_caption)
                            <figcaption class="mt-2 text-sm text-slate-600">{{ $survey->intro_image_caption }}</figcaption>
                        @endif
                    </figure>
                @endif
                @if ($survey->intro_text)
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $survey->intro_text }}</p>
                @endif
                @if ($survey->privacy_statement)
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold">Privacy Statement</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $survey->privacy_statement }}</p>
                    </div>
                @endif
                @if ($survey->respondent_instruction)
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold">Respondent Instruction</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $survey->respondent_instruction }}</p>
                    </div>
                @endif
                @if ($survey->consent_text)
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold">Consent Text</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $survey->consent_text }}</p>
                    </div>
                @endif

                <div class="mt-5 rounded-lg border border-indigo-100 bg-indigo-50 p-4">
                    <p class="text-sm font-semibold">Intro-level comment</p>
                    <input type="hidden" name="comments[intro][comment_type]" value="{{ SurveySupervisorReviewComment::TYPE_INTRO }}">
                    <input type="hidden" name="comments[intro][target_key]" value="intro">
                    <input type="hidden" name="comments[intro][target_label]" value="Intro narrative, image, privacy, instruction, consent">
                    <textarea name="comments[intro][comment]" rows="3" placeholder="Comment on intro narrative, image, privacy statement, instruction, or consent text" class="mt-2 block w-full rounded-md border-slate-300"></textarea>
                    <textarea name="comments[intro][suggested_revision]" rows="2" placeholder="Suggested revision" class="mt-2 block w-full rounded-md border-slate-300"></textarea>
                </div>
            </section>

            @foreach ($pages as $page)
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Section {{ $loop->iteration }}</p>
                        <h2 class="mt-1 text-xl font-semibold">{{ $page->title }}</h2>
                        @if ($page->description)
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $page->description }}</p>
                        @endif
                    </div>

                    <div class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50 p-4">
                        <p class="text-sm font-semibold">Section-level comment</p>
                        <input type="hidden" name="comments[section_{{ $page->id }}][comment_type]" value="{{ SurveySupervisorReviewComment::TYPE_SECTION }}">
                        <input type="hidden" name="comments[section_{{ $page->id }}][target_key]" value="{{ $page->id }}">
                        <input type="hidden" name="comments[section_{{ $page->id }}][target_label]" value="{{ $page->title }}">
                        <textarea name="comments[section_{{ $page->id }}][comment]" rows="2" placeholder="Section comment" class="mt-2 block w-full rounded-md border-slate-300"></textarea>
                        <textarea name="comments[section_{{ $page->id }}][suggested_revision]" rows="2" placeholder="Suggested revision" class="mt-2 block w-full rounded-md border-slate-300"></textarea>
                    </div>

                    <div class="mt-5 space-y-4">
                        @foreach ($page->questions->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)->sortBy('sort_order') as $question)
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <span>{{ $question->question_key ?: 'No key' }}</span>
                                    <span>{{ str($question->type)->replace('_', ' ')->title() }}</span>
                                    <span>{{ $question->is_required ? 'Required' : 'Optional' }}</span>
                                </div>
                                <p class="mt-2 font-semibold">{{ $question->label }}</p>
                                @if ($choiceLabels($question))
                                    <p class="mt-2 text-sm text-slate-600">Options/scale: {{ $choiceLabels($question) }}</p>
                                @endif
                                @if ($question->scoring)
                                    <p class="mt-1 text-sm text-slate-600">Indicator/scoring: {{ $question->scoring->indicator?->name ?: 'No indicator' }} · {{ $question->scoring->is_scored ? 'Scored' : 'Descriptive' }}</p>
                                @endif

                                <div class="mt-4 grid gap-3 md:grid-cols-3">
                                    <input type="hidden" name="comments[item_{{ $question->id }}][comment_type]" value="{{ SurveySupervisorReviewComment::TYPE_ITEM }}">
                                    <input type="hidden" name="comments[item_{{ $question->id }}][survey_question_id]" value="{{ $question->id }}">
                                    <input type="hidden" name="comments[item_{{ $question->id }}][target_key]" value="{{ $question->question_key }}">
                                    <input type="hidden" name="comments[item_{{ $question->id }}][target_label]" value="{{ $question->label }}">
                                    <label>
                                        <span class="text-sm font-medium text-slate-700">Severity</span>
                                        <select name="comments[item_{{ $question->id }}][severity]" class="mt-1 block w-full rounded-md border-slate-300">
                                            @foreach ($severities as $severity)
                                                <option value="{{ $severity }}">{{ str($severity)->title() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span class="text-sm font-medium text-slate-700">Item decision</span>
                                        <select name="comments[item_{{ $question->id }}][decision]" class="mt-1 block w-full rounded-md border-slate-300">
                                            @foreach ($itemDecisions as $decision)
                                                <option value="{{ $decision }}">{{ str($decision)->title() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <div></div>
                                    <label class="md:col-span-3">
                                        <span class="text-sm font-medium text-slate-700">Item-level comment</span>
                                        <textarea name="comments[item_{{ $question->id }}][comment]" rows="2" class="mt-1 block w-full rounded-md border-slate-300"></textarea>
                                    </label>
                                    <label class="md:col-span-3">
                                        <span class="text-sm font-medium text-slate-700">Suggested revision</span>
                                        <textarea name="comments[item_{{ $question->id }}][suggested_revision]" rows="2" class="mt-1 block w-full rounded-md border-slate-300"></textarea>
                                    </label>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if ($questionsWithoutPage->isNotEmpty())
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">Questions Without Section</h2>
                    @foreach ($questionsWithoutPage as $question)
                        <article class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="font-semibold">{{ $question->question_key }} · {{ $question->label }}</p>
                            <input type="hidden" name="comments[item_{{ $question->id }}][comment_type]" value="{{ SurveySupervisorReviewComment::TYPE_ITEM }}">
                            <input type="hidden" name="comments[item_{{ $question->id }}][survey_question_id]" value="{{ $question->id }}">
                            <input type="hidden" name="comments[item_{{ $question->id }}][target_key]" value="{{ $question->question_key }}">
                            <input type="hidden" name="comments[item_{{ $question->id }}][target_label]" value="{{ $question->label }}">
                            <textarea name="comments[item_{{ $question->id }}][comment]" rows="2" class="mt-3 block w-full rounded-md border-slate-300"></textarea>
                            <textarea name="comments[item_{{ $question->id }}][suggested_revision]" rows="2" placeholder="Suggested revision" class="mt-2 block w-full rounded-md border-slate-300"></textarea>
                        </article>
                    @endforeach
                </section>
            @endif

            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Overall Recommendation</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach ([
                        'academic_suitability' => 'Overall academic suitability',
                        'language_clarity' => 'Language clarity',
                        'content_relevance' => 'Content relevance',
                        'methodological_suitability' => 'Methodological suitability',
                        'ethical_privacy' => 'Ethical/privacy concerns',
                        'readiness' => 'Readiness for expert validation',
                    ] as $key => $label)
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                            <input type="hidden" name="comments[overall_{{ $key }}][comment_type]" value="{{ SurveySupervisorReviewComment::TYPE_OVERALL }}">
                            <input type="hidden" name="comments[overall_{{ $key }}][target_key]" value="{{ $key }}">
                            <input type="hidden" name="comments[overall_{{ $key }}][target_label]" value="{{ $label }}">
                            <textarea name="comments[overall_{{ $key }}][comment]" rows="3" class="mt-1 block w-full rounded-md border-slate-300"></textarea>
                        </label>
                    @endforeach
                </div>

                <label class="mt-5 block">
                    <span class="text-sm font-medium text-slate-700">Final decision</span>
                    <select name="final_decision" required class="mt-1 block w-full rounded-md border-slate-300">
                        @foreach ($decisions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-4 block">
                    <span class="text-sm font-medium text-slate-700">Final notes</span>
                    <textarea name="final_notes" rows="4" class="mt-1 block w-full rounded-md border-slate-300"></textarea>
                </label>
                <button class="mt-5 rounded-md bg-indigo-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-600">Submit Supervisor Review</button>
            </section>
        </form>
    </main>
</body>
</html>
