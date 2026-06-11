<?php

namespace App\Modules\Surveys\Services;

use App\Models\SurveyQuestion;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SurveyQuestionOptionsValidationService
{
    /**
     * @return array{options: array<string, mixed>|array<int, mixed>|null, settings: array<string, mixed>|null}
     */
    public function validate(string $type, ?string $optionsJson, ?string $settingsJson): array
    {
        $options = $this->decodeJson('options', $optionsJson);
        $settings = $this->decodeJson('settings', $settingsJson);

        match ($type) {
            SurveyQuestion::TYPE_SINGLE_CHOICE, SurveyQuestion::TYPE_MULTIPLE_CHOICE => $this->validateChoices($options),
            SurveyQuestion::TYPE_LIKERT => $this->validateLikert($options, $settings),
            SurveyQuestion::TYPE_LIKERT_MATRIX => $this->validateLikertMatrix($options, $settings),
            default => null,
        };

        return [
            'options' => $options,
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function decodeJson(string $field, ?string $json): ?array
    {
        if (blank($json)) {
            return null;
        }

        try {
            $decoded = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                $field => ucfirst($field).' must be valid JSON.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => ucfirst($field).' must decode to a JSON object or array.',
            ]);
        }

        if ($this->containsUnsafeString($decoded)) {
            throw ValidationException::withMessages([
                $field => ucfirst($field).' cannot contain script markup.',
            ]);
        }

        return $decoded;
    }

    private function validateChoices(?array $options): void
    {
        $choices = $this->choiceValues($options);

        if ($choices === []) {
            throw ValidationException::withMessages([
                'options' => 'Choice questions require options with at least one choice.',
            ]);
        }
    }

    private function validateLikert(?array $options, ?array $settings): void
    {
        $scale = Arr::get($settings ?? [], 'scale', Arr::get($options ?? [], 'scale'));

        if ($scale !== null && (! is_array($scale) || $scale === [])) {
            throw ValidationException::withMessages([
                'settings' => 'Likert scale must be a non-empty array when provided.',
            ]);
        }
    }

    private function validateLikertMatrix(?array $options, ?array $settings): void
    {
        $rows = Arr::get($options ?? [], 'rows', []);
        $columns = Arr::get($options ?? [], 'columns', Arr::get($settings ?? [], 'scale', []));

        if (! is_array($rows) || $rows === [] || ! is_array($columns) || $columns === []) {
            throw ValidationException::withMessages([
                'options' => 'Likert matrix requires non-empty rows and columns.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function choiceValues(?array $options): array
    {
        if (! $options) {
            return [];
        }

        $choices = array_is_list($options) ? $options : Arr::get($options, 'choices', Arr::get($options, 'options', []));

        if (! is_array($choices)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (mixed $choice): string => is_array($choice)
            ? trim((string) ($choice['value'] ?? $choice['label'] ?? ''))
            : trim((string) $choice), $choices)));
    }

    private function containsUnsafeString(array $values): bool
    {
        foreach ($values as $value) {
            if (is_array($value) && $this->containsUnsafeString($value)) {
                return true;
            }

            if (is_string($value) && preg_match('/<\s*script\b/i', $value)) {
                return true;
            }
        }

        return false;
    }
}
