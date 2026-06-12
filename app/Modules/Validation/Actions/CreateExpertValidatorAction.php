<?php

namespace App\Modules\Validation\Actions;

use App\Models\ExpertValidator;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CreateExpertValidatorAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, ?Request $request = null): ExpertValidator
    {
        Gate::forUser($user)->authorize('create', ExpertValidator::class);

        $expertValidator = ExpertValidator::create([
            'created_by' => $user->getKey(),
            'name' => (string) $attributes['name'],
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'institution' => $attributes['institution'] ?? null,
            'position' => $attributes['position'] ?? null,
            'expertise_areas' => $this->expertiseAreas($attributes['expertise_areas'] ?? []),
            'notes' => $attributes['notes'] ?? null,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'is_global' => $user->hasRole('super_admin') && (bool) ($attributes['is_global'] ?? false),
        ]);

        $this->activityLogger->log('expert_validator.created', $user, null, $expertValidator, $this->metadata($expertValidator), $request);

        return $expertValidator;
    }

    /**
     * @return array<int, string>
     */
    private function expertiseAreas(mixed $areas): array
    {
        if (! is_array($areas)) {
            return [];
        }

        return collect($areas)
            ->map(fn (mixed $area): string => trim((string) $area))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(ExpertValidator $expertValidator): array
    {
        return [
            'expert_validator_id' => $expertValidator->getKey(),
            'is_active' => $expertValidator->is_active,
            'is_global' => $expertValidator->is_global,
            'expertise_area_count' => count($expertValidator->expertise_areas ?? []),
        ];
    }
}
