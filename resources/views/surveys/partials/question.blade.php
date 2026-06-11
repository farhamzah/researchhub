@php
    $key = $question->question_key;
    $options = $question->options ?? [];
    $choices = array_is_list($options) ? $options : ($options['choices'] ?? $options['options'] ?? []);
    $choiceValue = fn ($choice) => is_array($choice) ? (string) ($choice['value'] ?? $choice['label'] ?? '') : (string) $choice;
    $choiceLabel = fn ($choice) => is_array($choice) ? (string) ($choice['label'] ?? $choice['value'] ?? '') : (string) $choice;
    $scale = $question->settings['scale'] ?? $options['scale'] ?? config('researchhub_surveys.default_likert_scale', [1, 2, 3, 4, 5]);
    $rows = $options['rows'] ?? [];
    $columns = $options['columns'] ?? $scale;
@endphp

@if ($question->type === \App\Models\SurveyQuestion::TYPE_HIDDEN)
    <input type="hidden" name="answers[{{ $key }}]" value="{{ old("answers.{$key}", $question->settings['value'] ?? '') }}">
@else
    <div>
        <label class="block text-sm font-semibold text-gray-900" for="question_{{ $key }}">
            {{ $question->label }}
            @if ($question->is_required)
                <span class="text-red-600">*</span>
            @endif
        </label>
        @if ($question->help_text)
            <p class="mt-1 text-sm text-gray-600">{{ $question->help_text }}</p>
        @endif

        <div class="mt-3">
            @switch($question->type)
                @case(\App\Models\SurveyQuestion::TYPE_LONG_TEXT)
                    <textarea id="question_{{ $key }}" name="answers[{{ $key }}]" rows="4" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old("answers.{$key}") }}</textarea>
                    @break

                @case(\App\Models\SurveyQuestion::TYPE_SINGLE_CHOICE)
                    <div class="space-y-2">
                        @foreach ($choices as $choice)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="answers[{{ $key }}]" value="{{ $choiceValue($choice) }}" @checked(old("answers.{$key}") === $choiceValue($choice)) class="border-gray-300 text-emerald-700">
                                {{ $choiceLabel($choice) }}
                            </label>
                        @endforeach
                    </div>
                    @break

                @case(\App\Models\SurveyQuestion::TYPE_MULTIPLE_CHOICE)
                    <div class="space-y-2">
                        @foreach ($choices as $choice)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="answers[{{ $key }}][]" value="{{ $choiceValue($choice) }}" @checked(in_array($choiceValue($choice), old("answers.{$key}", []), true)) class="rounded border-gray-300 text-emerald-700">
                                {{ $choiceLabel($choice) }}
                            </label>
                        @endforeach
                    </div>
                    @break

                @case(\App\Models\SurveyQuestion::TYPE_LIKERT)
                    <div class="flex flex-wrap gap-3">
                        @foreach ($scale as $scaleValue)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="answers[{{ $key }}]" value="{{ $scaleValue }}" @checked((string) old("answers.{$key}") === (string) $scaleValue) class="border-gray-300 text-emerald-700">
                                {{ $scaleValue }}
                            </label>
                        @endforeach
                    </div>
                    @break

                @case(\App\Models\SurveyQuestion::TYPE_LIKERT_MATRIX)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="py-2 pr-3 text-left font-medium text-gray-500">Item</th>
                                    @foreach ($columns as $column)
                                        <th class="px-3 py-2 text-center font-medium text-gray-500">{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="py-2 pr-3 text-gray-700">{{ $row }}</td>
                                        @foreach ($columns as $column)
                                            <td class="px-3 py-2 text-center">
                                                <input type="radio" name="answers[{{ $key }}][{{ $row }}]" value="{{ $column }}" @checked((string) old("answers.{$key}.{$row}") === (string) $column) class="border-gray-300 text-emerald-700">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @break

                @case(\App\Models\SurveyQuestion::TYPE_NUMBER)
                    <input id="question_{{ $key }}" name="answers[{{ $key }}]" type="number" step="any" value="{{ old("answers.{$key}") }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    @break

                @case(\App\Models\SurveyQuestion::TYPE_DATE)
                    <input id="question_{{ $key }}" name="answers[{{ $key }}]" type="date" value="{{ old("answers.{$key}") }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    @break

                @case(\App\Models\SurveyQuestion::TYPE_CONSENT)
                    <input type="hidden" name="answers[{{ $key }}]" value="0">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input id="question_{{ $key }}" type="checkbox" name="answers[{{ $key }}]" value="1" @checked(old("answers.{$key}") === '1') class="rounded border-gray-300 text-emerald-700">
                        I agree
                    </label>
                    @break

                @default
                    <input id="question_{{ $key }}" name="answers[{{ $key }}]" value="{{ old("answers.{$key}") }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
            @endswitch
        </div>

        @error("answers.{$key}")
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
@endif
