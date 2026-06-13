<?php

namespace App\Modules\Projects\Actions;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Models\ResearchLink;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyIndicator;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionScoring;
use App\Models\SurveyScale;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use App\Modules\Projects\Services\ProjectTemplateCatalogService;
use App\Modules\ResearchLinks\Services\ResearchLinkUrlSafetyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateProjectFromTemplateAction
{
    public function __construct(
        private readonly ProjectTemplateCatalogService $catalog,
        private readonly ActivityLogger $activityLogger,
        private readonly ResearchLinkUrlSafetyService $urlSafety,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, string $templateKey, array $attributes): ResearchProject
    {
        Gate::forUser($user)->authorize('create', ResearchProject::class);

        $template = $this->catalog->find($templateKey);
        $includeDocuments = (bool) ($attributes['include_documents'] ?? true);
        $includeSurvey = (bool) ($attributes['include_survey'] ?? true);
        $includeLinks = (bool) ($attributes['include_research_links'] ?? true);
        $startedAt = $this->dateOrToday($attributes['started_at'] ?? null);
        $targetFinishedAt = $this->dateOrDefault($attributes['target_finished_at'] ?? null, $startedAt, (int) $template['duration_days']);

        return DB::transaction(function () use ($user, $attributes, $templateKey, $template, $includeDocuments, $includeSurvey, $includeLinks, $startedAt, $targetFinishedAt): ResearchProject {
            $project = ResearchProject::create([
                'owner_id' => $user->getKey(),
                'title' => (string) ($attributes['title'] ?: $template['default_title']),
                'slug' => $this->uniqueProjectSlug((string) ($attributes['title'] ?: $template['default_title'])),
                'description' => $attributes['description'] ?? $template['description'],
                'research_type' => $template['name'],
                'status' => ResearchProject::STATUS_ACTIVE,
                'started_at' => $startedAt,
                'target_finished_at' => $targetFinishedAt,
            ]);

            $milestones = $this->createMilestones($project, $user, $template, $startedAt, $targetFinishedAt);
            $this->createTimelineTasks($project, $user, $template, $milestones, $startedAt, $targetFinishedAt);

            if ($includeDocuments) {
                $this->createDocuments($project, $user, $template);
            }

            if ($includeSurvey && is_array($template['survey'])) {
                $this->createSurvey($project, $user, $template['survey']);
            }

            if ($includeLinks) {
                $this->createResearchLinks($project, $user, $template);
            }

            $this->activityLogger->log(
                'project_template.applied',
                $user,
                $project,
                $project,
                [
                    'template_key' => $templateKey,
                    'research_project_id' => $project->getKey(),
                    'created_by_user_id' => $user->getKey(),
                ],
            );

            return $project->fresh(['documents', 'milestones', 'timelineTasks', 'surveys', 'researchLinks']);
        });
    }

    /**
     * @return array<int, ProjectMilestone>
     */
    private function createMilestones(ResearchProject $project, User $user, array $template, Carbon $startedAt, Carbon $targetFinishedAt): array
    {
        $milestoneTitles = $template['milestones'];
        $count = max(1, count($milestoneTitles));
        $daysPerMilestone = max(1, (int) ceil($startedAt->diffInDays($targetFinishedAt) / $count));
        $milestones = [];

        foreach ($milestoneTitles as $index => $title) {
            $plannedStart = $startedAt->copy()->addDays($index * $daysPerMilestone);
            $plannedEnd = $index === $count - 1
                ? $targetFinishedAt->copy()
                : $plannedStart->copy()->addDays($daysPerMilestone - 1);

            $milestones[] = ProjectMilestone::create([
                'research_project_id' => $project->getKey(),
                'title' => (string) $title,
                'description' => 'Starter milestone dari template '.$template['name'].'.',
                'planned_start_date' => $plannedStart,
                'planned_end_date' => $plannedEnd,
                'status' => $index === 0 ? ProjectMilestone::STATUS_IN_PROGRESS : ProjectMilestone::STATUS_NOT_STARTED,
                'sort_order' => $index + 1,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
        }

        return $milestones;
    }

    /**
     * @param  array<int, ProjectMilestone>  $milestones
     */
    private function createTimelineTasks(ResearchProject $project, User $user, array $template, array $milestones, Carbon $startedAt, Carbon $targetFinishedAt): void
    {
        $tasks = $template['tasks'];
        $count = max(1, count($tasks));
        $daysPerTask = max(1, (int) ceil($startedAt->diffInDays($targetFinishedAt) / $count));

        foreach ($tasks as $index => $title) {
            $milestone = $milestones[min($index, count($milestones) - 1)];
            $plannedStart = $startedAt->copy()->addDays($index * $daysPerTask);
            $plannedEnd = $index === $count - 1
                ? $targetFinishedAt->copy()
                : $plannedStart->copy()->addDays($daysPerTask - 1);

            ProjectTimelineTask::create([
                'research_project_id' => $project->getKey(),
                'project_milestone_id' => $milestone->getKey(),
                'title' => (string) $title,
                'description' => 'Starter task dari template '.$template['name'].'.',
                'planned_start_date' => $plannedStart,
                'planned_end_date' => $plannedEnd,
                'status' => $index === 0 ? ProjectMilestone::STATUS_IN_PROGRESS : ProjectMilestone::STATUS_NOT_STARTED,
                'progress_percentage' => 0,
                'weight' => 1,
                'sort_order' => $index + 1,
                'assigned_to' => $user->getKey(),
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
        }
    }

    private function createDocuments(ResearchProject $project, User $user, array $template): void
    {
        foreach ($template['documents'] as $index => $document) {
            $type = (string) ($document['type'] ?? Document::TYPE_OTHER);
            $category = $this->documentCategoryForType($type);

            Document::create([
                'project_id' => $project->getKey(),
                'category_id' => $category->getKey(),
                'owner_id' => $user->getKey(),
                'title' => (string) $document['title'],
                'slug' => $this->uniqueDocumentSlug($project, (string) $document['title']),
                'description' => 'Starter document metadata dari template '.$template['name'].'.',
                'status' => Document::STATUS_DRAFT,
                'visibility' => Document::VISIBILITY_PROJECT,
                'document_type' => in_array($type, Document::TYPES, true) ? $type : Document::TYPE_OTHER,
                'version_label' => 'v01',
                'version_number' => 1,
                'is_current' => true,
                'next_action' => 'Sesuaikan dokumen dengan kebutuhan project.',
                'revision_summary' => 'Starter draft dari template. Belum ada revisi akademik.',
                'tags' => ['template', Str::slug($template['name']), 'starter'],
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }
    }

    private function createSurvey(ResearchProject $project, User $user, array $surveyTemplate): Survey
    {
        $survey = Survey::create([
            'project_id' => $project->getKey(),
            'created_by' => $user->getKey(),
            'title' => (string) $surveyTemplate['title'],
            'slug' => $this->uniqueSurveySlug((string) $surveyTemplate['title']),
            'description' => 'Starter survey dari template project. Belum dipublikasikan.',
            'status' => Survey::STATUS_DRAFT,
            'identity_mode' => Survey::IDENTITY_HIDDEN,
            'is_public' => false,
        ]);

        $scale = SurveyScale::create([
            'survey_id' => $survey->getKey(),
            'name' => 'Skala Likert 1-5',
            'slug' => 'skala-likert-1-5',
            'description' => 'Starter scale untuk evaluasi template.',
            'sort_order' => 1,
            'settings' => ['min' => 1, 'max' => 5],
        ]);

        $indicators = collect($surveyTemplate['indicators'] ?? ['Kelayakan'])
            ->values()
            ->mapWithKeys(fn (string $name, int $index): array => [
                $index => SurveyIndicator::create([
                    'survey_id' => $survey->getKey(),
                    'survey_scale_id' => $scale->getKey(),
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => 'Starter indicator dari template.',
                    'sort_order' => $index + 1,
                ]),
            ]);

        $page = SurveyPage::create([
            'survey_id' => $survey->getKey(),
            'title' => 'Evaluasi Awal',
            'description' => 'Starter questions dari template project.',
            'sort_order' => 1,
        ]);

        foreach (array_values($surveyTemplate['questions'] ?? []) as $index => $label) {
            $question = SurveyQuestion::create([
                'survey_id' => $survey->getKey(),
                'page_id' => $page->getKey(),
                'question_key' => Str::slug((string) $label, '_'),
                'type' => SurveyQuestion::TYPE_LIKERT,
                'label' => (string) $label,
                'help_text' => 'Pilih skor sesuai pengalaman atau penilaian awal.',
                'options' => ['scale' => [1, 2, 3, 4, 5]],
                'settings' => ['min_label' => 'Sangat tidak setuju', 'max_label' => 'Sangat setuju'],
                'is_required' => true,
                'sort_order' => $index + 1,
            ]);

            SurveyQuestionScoring::create([
                'survey_id' => $survey->getKey(),
                'survey_question_id' => $question->getKey(),
                'survey_indicator_id' => $indicators->get($index % max(1, $indicators->count()))?->getKey(),
                'is_scored' => true,
                'score_min' => 1,
                'score_max' => 5,
                'weight' => 1,
                'is_reverse_scored' => false,
            ]);
        }

        return $survey;
    }

    private function createResearchLinks(ResearchProject $project, User $user, array $template): void
    {
        foreach ($template['research_links'] as $index => $link) {
            $url = $this->urlSafety->assertSafe((string) $link['url']);

            ResearchLink::create([
                'research_project_id' => $project->getKey(),
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
                'title' => (string) $link['title'],
                'url' => $url,
                'description' => 'Starter research link dari template '.$template['name'].'.',
                'category' => in_array($link['category'], ResearchLink::CATEGORIES, true) ? $link['category'] : ResearchLink::CATEGORY_REFERENCE,
                'is_pinned' => true,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function documentCategoryForType(string $type): DocumentCategory
    {
        $label = Document::TYPE_LABELS[$type] ?? 'Other';

        return DocumentCategory::query()->firstOrCreate(
            ['slug' => Str::slug($label)],
            [
                'name' => $label,
                'description' => 'Kategori starter untuk template project MyRiset.',
                'sort_order' => 90,
                'is_default' => false,
            ],
        );
    }

    private function dateOrToday(mixed $value): Carbon
    {
        return filled($value) ? Carbon::parse($value)->startOfDay() : today();
    }

    private function dateOrDefault(mixed $value, Carbon $startedAt, int $durationDays): Carbon
    {
        return filled($value)
            ? Carbon::parse($value)->startOfDay()
            : $startedAt->copy()->addDays($durationDays);
    }

    private function uniqueProjectSlug(string $title): string
    {
        return $this->uniqueSlug(ResearchProject::query(), $title);
    }

    private function uniqueDocumentSlug(ResearchProject $project, string $title): string
    {
        return $this->uniqueSlug(Document::query()->where('project_id', $project->getKey()), $title);
    }

    private function uniqueSurveySlug(string $title): string
    {
        return $this->uniqueSlug(Survey::query(), $title);
    }

    private function uniqueSlug($query, string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'template-item';
        $slug = $baseSlug;
        $suffix = 2;

        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
