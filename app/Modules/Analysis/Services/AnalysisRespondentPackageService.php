<?php

namespace App\Modules\Analysis\Services;

use App\Models\AnalysisDocumentPackage;
use App\Models\AnalysisPilotRun;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Surveys\Services\SurveyDistributionCenterService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnalysisRespondentPackageService
{
    public function __construct(
        private readonly AnalysisPreflightQaService $preflightQaService,
        private readonly AnalysisCollectionMonitoringService $collectionMonitoringService,
        private readonly SurveyDistributionCenterService $distributionCenterService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey, User $user): array
    {
        $survey->load([
            'project',
            'analysisPreflightReviews.reviewer',
            'analysisDocumentPackage',
            'analysisPilotRuns.responses',
            'validationRounds.assignments.validator',
            'readabilityRounds.participants',
        ]);

        $instruments = $this->analysisInstruments($survey);
        $preflight = $this->preflightQaService->build($survey, $user);
        $collection = $this->collectionMonitoringService->build($survey, $user);
        $distribution = $this->distributionCenterService->build($survey, $user);
        $pilotRuns = $survey->analysisPilotRuns()
            ->with(['targetSurvey', 'responses'])
            ->latest()
            ->get()
            ->groupBy('audience_type');

        $pilotRows = $this->pilotRows($survey, $instruments, $pilotRuns);

        return [
            'nav' => $this->nav($survey),
            'preflight' => $preflight,
            'collection' => $collection,
            'distribution' => $distribution,
            'package' => $survey->analysisDocumentPackage,
            'real_links' => $this->realLinks($distribution),
            'validation_links' => $this->validationLinks($survey),
            'readability_links' => $this->readabilityLinks($survey),
            'pilot_rows' => $pilotRows,
            'test_response_summary' => $this->testResponseSummary($instruments),
            'launch' => $this->launchReadiness($survey, $preflight, $collection, $distribution, $pilotRows),
            'generated_at' => now(),
        ];
    }

    /**
     * @return array{run: AnalysisPilotRun, token: string, url: string}
     */
    public function generatePilotLink(Survey $survey, string $audienceType, User $user): array
    {
        $targetSurvey = $this->targetSurveyForAudience($survey, $audienceType);

        abort_if(! $targetSurvey, 422, 'Target survey is missing.');

        $token = Str::random(64);
        $run = AnalysisPilotRun::create([
            'survey_id' => $survey->getKey(),
            'project_id' => $survey->project_id,
            'target_survey_id' => $targetSurvey->getKey(),
            'audience_type' => $audienceType,
            'token_hash' => $this->hashToken($token),
            'status' => AnalysisPilotRun::STATUS_ACTIVE,
            'checklist_json' => $this->emptyChecklist(),
            'generated_by' => $user->getKey(),
            'expires_at' => now()->addDays(7),
        ]);

        return [
            'run' => $run,
            'token' => $token,
            'url' => $this->pilotUrl($targetSurvey, $token),
        ];
    }

    public function resolvePilotRun(Survey $targetSurvey, ?string $token): ?AnalysisPilotRun
    {
        if (blank($token)) {
            return null;
        }

        return AnalysisPilotRun::query()
            ->active()
            ->where('target_survey_id', $targetSurvey->getKey())
            ->where('token_hash', $this->hashToken($token))
            ->first();
    }

    public function revokePilotRun(Survey $survey, AnalysisPilotRun $pilotRun): void
    {
        abort_unless($pilotRun->survey_id === $survey->getKey(), 404);

        $pilotRun->update([
            'status' => AnalysisPilotRun::STATUS_REVOKED,
            'revoked_at' => now(),
            'token_hash' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateChecklist(Survey $survey, AnalysisPilotRun $pilotRun, array $data): AnalysisPilotRun
    {
        abort_unless($pilotRun->survey_id === $survey->getKey(), 404);

        $checklist = $this->emptyChecklist();

        foreach (array_keys($checklist) as $key) {
            if ($key === 'notes') {
                continue;
            }

            $checklist[$key] = (bool) ($data[$key] ?? false);
        }

        $checklist['notes'] = $data['notes'] ?? null;
        $status = $pilotRun->status === AnalysisPilotRun::STATUS_REVOKED
            ? AnalysisPilotRun::STATUS_REVOKED
            : ($this->checklistPassed($checklist) ? AnalysisPilotRun::STATUS_PASSED : $pilotRun->status);

        $pilotRun->update([
            'checklist_json' => $checklist,
            'notes' => $checklist['notes'],
            'status' => $status,
            'passed_at' => $status === AnalysisPilotRun::STATUS_PASSED ? now() : $pilotRun->passed_at,
        ]);

        return $pilotRun->fresh();
    }

    public function markFailed(Survey $survey, AnalysisPilotRun $pilotRun, ?string $notes): void
    {
        abort_unless($pilotRun->survey_id === $survey->getKey(), 404);

        $pilotRun->update([
            'status' => AnalysisPilotRun::STATUS_FAILED,
            'notes' => $notes ?: $pilotRun->notes,
        ]);
    }

    public function markSubmitted(AnalysisPilotRun $pilotRun): void
    {
        if ($pilotRun->status === AnalysisPilotRun::STATUS_ACTIVE) {
            $pilotRun->update([
                'status' => AnalysisPilotRun::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
        }
    }

    public function clearTestResponses(Survey $survey, ?Survey $targetSurvey = null): int
    {
        $targetIds = $targetSurvey
            ? [$targetSurvey->getKey()]
            : collect($this->analysisInstruments($survey))->filter()->map->getKey()->values()->all();

        if ($targetIds === []) {
            return 0;
        }

        $responses = SurveyResponse::query()
            ->whereIn('survey_id', $targetIds)
            ->testData()
            ->get();
        $count = $responses->count();

        foreach ($responses as $response) {
            $response->answers()->delete();
            $response->delete();
        }

        return $count;
    }

    /**
     * @return array<string, Survey|null>
     */
    public function analysisInstruments(Survey $survey): array
    {
        $groupKey = $survey->analysis_group_key ?: Survey::ANALYSIS_GROUP_PHARMVR_ADDIE;
        $related = Survey::query()
            ->with(['questions', 'responses'])
            ->where('project_id', $survey->project_id)
            ->where(function ($query) use ($survey, $groupKey): void {
                $query
                    ->where('id', $survey->getKey())
                    ->orWhere('parent_survey_id', $survey->getKey())
                    ->orWhere('analysis_group_key', $groupKey);
            })
            ->get();

        return [
            AnalysisPilotRun::AUDIENCE_STUDENT => $related->firstWhere('id', $survey->getKey()) ?: $survey->fresh(['questions', 'responses']),
            AnalysisPilotRun::AUDIENCE_LECTURER => $related->firstWhere('instrument_type', Survey::INSTRUMENT_ANALYSIS_LECTURER),
            AnalysisPilotRun::AUDIENCE_PRACTITIONER => $related->firstWhere('instrument_type', Survey::INSTRUMENT_PRACTITIONER_INTERVIEW),
        ];
    }

    private function targetSurveyForAudience(Survey $survey, string $audienceType): ?Survey
    {
        abort_unless(in_array($audienceType, AnalysisPilotRun::AUDIENCES, true), 422);

        return $this->analysisInstruments($survey)[$audienceType] ?? null;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function pilotUrl(Survey $targetSurvey, string $token): string
    {
        return route('survey.show', ['survey' => $targetSurvey->slug, 'pilot' => $token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChecklist(): array
    {
        return [
            'intro_ok' => false,
            'consent_ok' => false,
            'questions_ok' => false,
            'required_validation_ok' => false,
            'submit_ok' => false,
            'thank_you_ok' => false,
            'excluded_from_analysis_ok' => false,
            'mobile_view_ok' => false,
            'desktop_view_ok' => false,
            'notes' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $checklist
     */
    private function checklistPassed(array $checklist): bool
    {
        foreach (AnalysisPilotRun::REQUIRED_CHECKLIST_KEYS as $key) {
            if (($checklist[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, Collection<int, AnalysisPilotRun>>  $pilotRuns
     * @return array<int, array<string, mixed>>
     */
    private function pilotRows(Survey $mainSurvey, array $instruments, Collection $pilotRuns): array
    {
        return collect(AnalysisPilotRun::AUDIENCES)
            ->map(function (string $audience) use ($mainSurvey, $instruments, $pilotRuns): array {
                $instrument = $instruments[$audience] ?? null;
                $latest = ($pilotRuns->get($audience) ?? collect())->first();
                $testResponses = $instrument
                    ? $instrument->responses->filter(fn (SurveyResponse $response): bool => $response->is_test_response || $response->excluded_from_analysis)
                    : collect();

                return [
                    'audience' => $audience,
                    'label' => AnalysisPilotRun::AUDIENCE_LABELS[$audience],
                    'instrument' => $instrument,
                    'latest_run' => $latest,
                    'status' => $latest?->status ?? 'not_generated',
                    'checklist' => $latest?->checklist_json ?? $this->emptyChecklist(),
                    'test_response_count' => $testResponses->count(),
                    'last_test_submission_at' => $testResponses->max('submitted_at'),
                    'generate_route' => route('admin.surveys.respondent-package.pilot.generate', ['survey' => $mainSurvey, 'audience' => $audience]),
                    'clear_route' => $instrument ? route('admin.surveys.respondent-package.test-responses.clear-target', ['survey' => $mainSurvey, 'targetSurvey' => $instrument]) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function realLinks(array $distribution): array
    {
        return collect($distribution['instruments'] ?? [])
            ->map(fn (array $panel): array => [
                'instrument' => $panel['survey']?->title ?? $panel['label'],
                'audience' => $panel['label'],
                'link' => $panel['link'],
                'status' => $panel['is_ready'] ? 'Ready' : 'Not ready',
                'whatsapp_message' => $panel['whatsapp_message'],
                'email_message' => $panel['email_message'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validationLinks(Survey $survey): array
    {
        return $survey->validationRounds
            ->flatMap->assignments
            ->map(fn ($assignment): array => [
                'name' => $assignment->validator?->name ?? 'Validator',
                'email' => $assignment->validator?->email,
                'status' => $assignment->status,
                'has_link' => filled($assignment->token_hash),
                'guidance' => 'Regenerate from Validation or Distribution Center to copy a fresh validator link.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readabilityLinks(Survey $survey): array
    {
        return $survey->readabilityRounds
            ->flatMap->participants
            ->map(fn ($participant): array => [
                'name' => $participant->participant_name ?? 'Readability participant',
                'email' => $participant->participant_email,
                'status' => $participant->status,
                'has_link' => filled($participant->token_hash),
                'guidance' => 'Regenerate from Readability or Distribution Center to copy a fresh readability link.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function testResponseSummary(array $instruments): array
    {
        return collect($instruments)
            ->map(function (?Survey $instrument, string $audience): array {
                $responses = $instrument
                    ? $instrument->responses->filter(fn (SurveyResponse $response): bool => $response->is_test_response || $response->excluded_from_analysis)
                    : collect();

                return [
                    'audience' => $audience,
                    'label' => AnalysisPilotRun::AUDIENCE_LABELS[$audience],
                    'instrument' => $instrument,
                    'count' => $responses->count(),
                    'last_submitted_at' => $responses->max('submitted_at'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function launchReadiness(Survey $survey, array $preflight, array $collection, array $distribution, array $pilotRows): array
    {
        $hasCritical = ($preflight['summary']['critical_failed'] ?? 0) > 0;
        $allPilotsPassed = collect($pilotRows)->every(fn (array $row): bool => $row['status'] === AnalysisPilotRun::STATUS_PASSED);
        $anyPilotStarted = collect($pilotRows)->contains(fn (array $row): bool => $row['latest_run'] instanceof AnalysisPilotRun);
        $allRealLinksReady = collect($distribution['instruments'] ?? [])->every(fn (array $panel): bool => ($panel['is_ready'] ?? false) === true);

        $recommendation = match (true) {
            $hasCritical => 'Not Ready',
            $allPilotsPassed && $allRealLinksReady => 'Ready for Real Distribution',
            $allPilotsPassed => 'Pilot Passed',
            $anyPilotStarted => 'Pilot In Progress',
            default => 'Ready for Pilot',
        };

        return [
            'recommendation' => $recommendation,
            'preflight_status' => $preflight['overall_status'],
            'last_preflight_review_at' => $survey->analysisPreflightReviews->sortByDesc('reviewed_at')->first()?->reviewed_at,
            'analysis_package_status' => AnalysisDocumentPackage::STATUS_LABELS[$survey->analysisDocumentPackage?->status] ?? 'Missing',
            'distribution_status' => $allRealLinksReady ? 'Final respondent links available' : 'Some final links are not ready',
            'collection_status' => $collection['readiness']['status'] ?? 'Not Ready',
            'pilot_status' => $allPilotsPassed ? 'All pilot tests passed' : ($anyPilotStarted ? 'Pilot in progress' : 'Not started'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function nav(Survey $survey): array
    {
        return [
            'Builder' => route('admin.surveys.builder.index', ['survey' => $survey]),
            'Analysis' => route('admin.surveys.analysis.index', ['survey' => $survey]),
            'Distribution' => route('admin.surveys.distribution.index', ['survey' => $survey]),
            'Collection Monitoring' => route('admin.surveys.collection-monitoring.index', ['survey' => $survey]),
            'Analysis Package' => route('admin.surveys.analysis-package.index', ['survey' => $survey]),
            'Preflight QA' => route('admin.surveys.preflight.index', ['survey' => $survey]),
            'Respondent Package' => route('admin.surveys.respondent-package.index', ['survey' => $survey]),
            'Back to Surveys' => route('filament.admin.resources.surveys.index'),
        ];
    }
}
