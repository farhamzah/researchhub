<?php

namespace App\Modules\Surveys\Actions;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyWorkflowTransition;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Surveys\Services\PharmVrInstrumentV2Catalog;
use App\Modules\Surveys\Services\SurveyInstrumentWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallPharmVrInstrumentsV2Action
{
    public function __construct(
        private readonly PharmVrInstrumentV2Catalog $catalog,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function preview(ResearchProject $project, string $contact): array
    {
        return collect($this->catalog->instruments($contact))->map(function (array $definition) use ($project): array {
            $existing = Survey::query()->where('project_id', $project->getKey())->where('instrument_code', $definition['code'])->where('instrument_version', PharmVrInstrumentV2Catalog::VERSION)->first();

            return ['identifier' => $definition['identifier'], 'title' => $definition['title'], 'action' => $existing ? 'existing' : 'create'];
        })->all();
    }

    public function handle(User $actor, ResearchProject $project, string $contact): array
    {
        return DB::transaction(function () use ($actor, $project, $contact): array {
            return collect($this->catalog->instruments($contact))->map(function (array $definition) use ($actor, $project): array {
                $existing = Survey::query()->where('project_id', $project->getKey())->where('instrument_code', $definition['code'])->where('instrument_version', PharmVrInstrumentV2Catalog::VERSION)->first();
                if ($existing) {
                    return ['survey' => $existing, 'created' => false];
                }

                $survey = Survey::create([
                    'project_id' => $project->getKey(), 'created_by' => $actor->getKey(), 'title' => $definition['title'],
                    'slug' => $this->uniqueSlug($definition['identifier']), 'description' => $definition['target'],
                    ...$definition['intro'], 'schema' => ['scientific_spec' => 'PM_INSTRUMENT_SPEC_PHARMVR_v2', 'identifier' => $definition['identifier'], 'analysis_plan' => $definition['analysis'], 'target' => $definition['target']],
                    'status' => Survey::STATUS_DRAFT, 'identity_mode' => Survey::IDENTITY_PSEUDONYM,
                    'instrument_type' => $definition['instrument_type'], 'instrument_code' => $definition['code'],
                    'instrument_version' => PharmVrInstrumentV2Catalog::VERSION, 'workflow_state' => SurveyInstrumentWorkflowService::DRAFT,
                    'analysis_group_key' => Survey::ANALYSIS_GROUP_PHARMVR_ADDIE, 'is_public' => false, 'published_at' => null,
                ]);

                $sortOrder = 0;
                foreach ($definition['pages'] as $pageIndex => $pageDefinition) {
                    $page = $survey->pages()->create([
                        'title' => $pageDefinition['title'],
                        'description' => $pageDefinition['description'] ?? null,
                        'sort_order' => $pageIndex + 1,
                    ]);
                    foreach ($pageDefinition['questions'] as $questionDefinition) {
                        $sortOrder++;
                        $survey->questions()->create([
                            'page_id' => $page->getKey(), 'question_key' => $questionDefinition['key'], 'type' => $questionDefinition['type'],
                            'label' => $questionDefinition['label'], 'options' => $questionDefinition['options'] === null ? null : ['choices' => $questionDefinition['options']],
                            'settings' => $questionDefinition['settings'], 'is_required' => $questionDefinition['required'], 'sort_order' => $sortOrder,
                        ]);
                    }
                }

                SurveyWorkflowTransition::create([
                    'survey_id' => $survey->getKey(), 'actor_id' => $actor->getKey(), 'actor_label' => $actor->name,
                    'from_state' => null, 'to_state' => SurveyInstrumentWorkflowService::DRAFT,
                    'instrument_version' => PharmVrInstrumentV2Catalog::VERSION, 'comment' => 'Instalasi idempotent draft instrumen PharmVR v2.0.',
                ]);
                $this->activityLogger->log('survey.pharmvr_v2.installed', $actor, $project, $survey, ['identifier' => $definition['identifier'], 'question_count' => $sortOrder]);

                return ['survey' => $survey->fresh(['pages.questions']), 'created' => true];
            })->all();
        });
    }

    private function uniqueSlug(string $identifier): string
    {
        $base = Str::slug($identifier);
        $slug = $base;
        $counter = 2;
        while (Survey::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
