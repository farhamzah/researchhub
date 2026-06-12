<?php

namespace App\Modules\Supervision\Actions;

use App\Models\SupervisionFeedback;
use App\Models\SupervisionReviewLink;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubmitSupervisionFeedbackAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SupervisionReviewLink $reviewLink, array $data, ?Request $request = null): SupervisionFeedback
    {
        $reviewLink->loadMissing('session.project', 'feedback');

        if (! $reviewLink->isAccessible()) {
            throw ValidationException::withMessages([
                'link' => 'This supervision review link is no longer available.',
            ]);
        }

        if ($reviewLink->feedback !== null) {
            throw ValidationException::withMessages([
                'link' => 'Feedback has already been submitted for this supervision link.',
            ]);
        }

        $feedback = SupervisionFeedback::create([
            'supervision_review_link_id' => $reviewLink->getKey(),
            'supervision_session_id' => $reviewLink->supervision_session_id,
            'decision' => $data['decision'],
            'general_feedback' => $data['general_feedback'] ?? null,
            'revision_notes' => $data['revision_notes'] ?? null,
            'recommended_next_steps' => $data['recommended_next_steps'] ?? null,
            'supervisor_note' => $data['supervisor_note'] ?? null,
        ]);

        $reviewLink->markSubmitted((string) $data['decision']);

        $this->activityLogger->log('supervision_feedback.submitted', null, $reviewLink->session->project, $feedback, [
            'supervision_session_id' => $reviewLink->supervision_session_id,
            'supervision_review_link_id' => $reviewLink->getKey(),
            'research_project_id' => $reviewLink->session->research_project_id,
            'expert_validator_id' => $reviewLink->expert_validator_id,
            'status' => $reviewLink->fresh()->status,
            'decision' => $feedback->decision,
        ], $request);

        return $feedback;
    }
}
