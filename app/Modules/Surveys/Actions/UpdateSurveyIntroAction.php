<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateSurveyIntroAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Survey $survey, array $attributes, ?Request $request = null): Survey
    {
        Gate::forUser($user)->authorize('update', $survey);

        $removeIntroImage = (bool) ($attributes['remove_intro_image'] ?? false);
        $uploadedIntroImage = $request?->file('intro_image');
        $introImagePath = $survey->intro_image_path;

        if ($removeIntroImage || $uploadedIntroImage !== null) {
            $this->deleteIntroImage($survey);
            $introImagePath = null;
        }

        if ($uploadedIntroImage !== null) {
            $introImagePath = $uploadedIntroImage->storeAs(
                "surveys/{$survey->getKey()}/intro",
                $this->introImageFilename($uploadedIntroImage->getClientOriginalExtension()),
                'public',
            );
        }

        $hasIntroImage = filled($introImagePath);

        $survey->fill([
            'intro_title' => $attributes['intro_title'] ?? null,
            'intro_text' => $attributes['intro_text'] ?? null,
            'estimated_duration' => $attributes['estimated_duration'] ?? null,
            'privacy_statement' => $attributes['privacy_statement'] ?? null,
            'respondent_instruction' => $attributes['respondent_instruction'] ?? null,
            'consent_text' => $attributes['consent_text'] ?? null,
            'require_consent_before_start' => (bool) ($attributes['require_consent_before_start'] ?? false),
            'intro_image_path' => $introImagePath,
            'intro_image_alt_text' => $hasIntroImage ? ($attributes['intro_image_alt_text'] ?? null) : null,
            'intro_image_caption' => $hasIntroImage ? ($attributes['intro_image_caption'] ?? null) : null,
            'intro_image_source_note' => $hasIntroImage ? ($attributes['intro_image_source_note'] ?? null) : null,
        ])->save();

        $this->activityLogger->log('survey.intro_updated', $user, $survey->project, $survey, [
            'survey_id' => $survey->getKey(),
            'requires_consent' => $survey->require_consent_before_start,
            'has_intro_image' => filled($survey->intro_image_path),
        ], $request);

        return $survey;
    }

    private function deleteIntroImage(Survey $survey): void
    {
        if (filled($survey->intro_image_path)) {
            Storage::disk('public')->delete($survey->intro_image_path);
        }
    }

    private function introImageFilename(?string $extension): string
    {
        $safeExtension = strtolower((string) $extension);

        if (! in_array($safeExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $safeExtension = 'jpg';
        }

        return 'intro-illustration-'.now()->format('YmdHis').'-'.Str::random(10).'.'.$safeExtension;
    }
}
