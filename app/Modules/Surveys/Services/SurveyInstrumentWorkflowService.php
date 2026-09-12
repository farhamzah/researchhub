<?php

namespace App\Modules\Surveys\Services;

use App\Models\Survey;
use App\Models\SurveyWorkflowTransition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyInstrumentWorkflowService
{
    public const DRAFT = 'draft';

    public const READY_FOR_SUPERVISOR_REVIEW = 'ready_for_supervisor_review';

    public const UNDER_SUPERVISOR_REVIEW = 'under_supervisor_review';

    public const SUPERVISOR_REVISION_REQUIRED = 'supervisor_revision_required';

    public const SUPERVISOR_APPROVED = 'supervisor_approved';

    public const READY_FOR_EXPERT_VALIDATION = 'ready_for_expert_validation';

    public const UNDER_EXPERT_VALIDATION = 'under_expert_validation';

    public const EXPERT_REVISION_REQUIRED = 'expert_revision_required';

    public const EXPERT_APPROVED = 'expert_approved';

    public const READY_FOR_READABILITY = 'ready_for_readability';

    public const READABILITY_REVISION_REQUIRED = 'readability_revision_required';

    public const READY_FOR_PILOT = 'ready_for_pilot';

    public const PILOT_IN_PROGRESS = 'pilot_in_progress';

    public const PILOT_REVISION_REQUIRED = 'pilot_revision_required';

    public const READY_FOR_MAIN_COLLECTION = 'ready_for_main_collection';

    public const STATES = [self::DRAFT, self::READY_FOR_SUPERVISOR_REVIEW, self::UNDER_SUPERVISOR_REVIEW, self::SUPERVISOR_REVISION_REQUIRED, self::SUPERVISOR_APPROVED, self::READY_FOR_EXPERT_VALIDATION, self::UNDER_EXPERT_VALIDATION, self::EXPERT_REVISION_REQUIRED, self::EXPERT_APPROVED, self::READY_FOR_READABILITY, self::READABILITY_REVISION_REQUIRED, self::READY_FOR_PILOT, self::PILOT_IN_PROGRESS, self::PILOT_REVISION_REQUIRED, self::READY_FOR_MAIN_COLLECTION];

    public const LABELS = [
        self::DRAFT => 'Draf', self::READY_FOR_SUPERVISOR_REVIEW => 'Siap Review Pembimbing', self::UNDER_SUPERVISOR_REVIEW => 'Sedang Direview Pembimbing', self::SUPERVISOR_REVISION_REQUIRED => 'Perlu Revisi Pembimbing', self::SUPERVISOR_APPROVED => 'Disetujui Pembimbing', self::READY_FOR_EXPERT_VALIDATION => 'Siap Validasi Ahli', self::UNDER_EXPERT_VALIDATION => 'Sedang Divalidasi Ahli', self::EXPERT_REVISION_REQUIRED => 'Perlu Revisi Ahli', self::EXPERT_APPROVED => 'Disetujui Ahli', self::READY_FOR_READABILITY => 'Siap Uji Keterbacaan', self::READABILITY_REVISION_REQUIRED => 'Perlu Revisi Keterbacaan', self::READY_FOR_PILOT => 'Siap Uji Coba Kecil', self::PILOT_IN_PROGRESS => 'Uji Coba Kecil Berjalan', self::PILOT_REVISION_REQUIRED => 'Perlu Revisi Uji Coba', self::READY_FOR_MAIN_COLLECTION => 'Siap Pengumpulan Data',
    ];

    private const ALLOWED = [
        self::DRAFT => [self::READY_FOR_SUPERVISOR_REVIEW],
        self::READY_FOR_SUPERVISOR_REVIEW => [self::UNDER_SUPERVISOR_REVIEW],
        self::UNDER_SUPERVISOR_REVIEW => [self::SUPERVISOR_REVISION_REQUIRED, self::SUPERVISOR_APPROVED],
        self::SUPERVISOR_APPROVED => [self::READY_FOR_EXPERT_VALIDATION],
        self::READY_FOR_EXPERT_VALIDATION => [self::UNDER_EXPERT_VALIDATION],
        self::UNDER_EXPERT_VALIDATION => [self::EXPERT_REVISION_REQUIRED, self::EXPERT_APPROVED],
        self::EXPERT_APPROVED => [self::READY_FOR_READABILITY],
        self::READY_FOR_READABILITY => [self::READABILITY_REVISION_REQUIRED, self::READY_FOR_PILOT],
        self::READY_FOR_PILOT => [self::PILOT_IN_PROGRESS],
        self::PILOT_IN_PROGRESS => [self::PILOT_REVISION_REQUIRED, self::READY_FOR_MAIN_COLLECTION],
    ];

    public function transition(Survey $survey, string $toState, ?User $actor = null, ?string $comment = null, ?string $actorLabel = null): Survey
    {
        $fromState = $survey->workflow_state ?: self::DRAFT;
        if (! in_array($toState, self::ALLOWED[$fromState] ?? [], true)) {
            throw ValidationException::withMessages(['workflow_state' => "Transisi {$fromState} ke {$toState} tidak diizinkan."]);
        }

        return DB::transaction(function () use ($survey, $fromState, $toState, $actor, $comment, $actorLabel): Survey {
            $survey->forceFill(['workflow_state' => $toState])->save();
            SurveyWorkflowTransition::create([
                'survey_id' => $survey->getKey(), 'actor_id' => $actor?->getKey(), 'actor_label' => $actorLabel,
                'from_state' => $fromState, 'to_state' => $toState,
                'instrument_version' => $survey->instrument_version ?: 'unversioned', 'comment' => $comment,
            ]);

            return $survey->refresh();
        });
    }

    public function progress(Survey $survey): array
    {
        $index = array_search($survey->workflow_state ?: self::DRAFT, self::STATES, true);
        $position = $index === false ? 1 : $index + 1;

        return ['current' => $position, 'total' => count(self::STATES), 'percent' => (int) round(($position / count(self::STATES)) * 100), 'label' => self::LABELS[$survey->workflow_state ?: self::DRAFT] ?? 'Draf', 'next_action' => $this->nextAction($survey)];
    }

    public function nextAction(Survey $survey): string
    {
        return match ($survey->workflow_state ?: self::DRAFT) {
            self::DRAFT => 'Kirim ke review pembimbing',
            self::READY_FOR_SUPERVISOR_REVIEW => 'Tetapkan pembimbing dan buka review',
            self::UNDER_SUPERVISOR_REVIEW => 'Tunggu keputusan pembimbing',
            self::SUPERVISOR_REVISION_REQUIRED => 'Buat draft versi revisi',
            self::SUPERVISOR_APPROVED => 'Siapkan validasi ahli',
            default => 'Lanjutkan sesuai gate workflow',
        };
    }
}
