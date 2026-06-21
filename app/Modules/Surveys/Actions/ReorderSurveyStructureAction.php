<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyPage;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Modules\AuditLogs\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReorderSurveyStructureAction
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<int, string>  $questionIds
     */
    public function reorderQuestions(User $user, Survey $survey, array $questionIds, ?string $pageId = null, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('update', $survey);
        $this->ensureCanReorder($survey);

        DB::transaction(function () use ($survey, $questionIds, $pageId, $user, $request): void {
            $targetPage = $this->targetPage($survey, $pageId);
            $orderedIds = collect($questionIds)->filter()->values();
            $questions = $survey->questions()->whereIn('id', $orderedIds->all())->get()->keyBy('id');

            if ($orderedIds->count() !== $questions->count()) {
                throw ValidationException::withMessages([
                    'question_order' => 'Question order contains invalid questions for this survey.',
                ]);
            }

            if ($targetPage instanceof SurveyPage) {
                foreach ($orderedIds as $questionId) {
                    $questions->get($questionId)?->forceFill(['page_id' => $targetPage->getKey()])->save();
                }
            }

            $this->renumberQuestions($survey, $orderedIds, $targetPage);

            $this->activityLogger->log('survey.questions_reordered', $user, $survey->project, $survey, [
                'survey_id' => $survey->getKey(),
                'question_ids' => $orderedIds->all(),
                'page_id' => $targetPage?->getKey(),
            ], $request);
        });
    }

    public function moveQuestion(User $user, SurveyQuestion $question, string $direction, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('update', $question->survey);
        $this->ensureCanReorder($question->survey);

        DB::transaction(function () use ($question, $direction, $user, $request): void {
            $survey = $question->survey;
            $questions = $survey->questions()->orderBy('sort_order')->orderBy('created_at')->get()->values();
            $index = $questions->search(fn (SurveyQuestion $candidate): bool => $candidate->is($question));

            if ($index === false) {
                return;
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($targetIndex < 0 || $targetIndex >= $questions->count()) {
                return;
            }

            $items = $questions->all();
            [$items[$index], $items[$targetIndex]] = [$items[$targetIndex], $items[$index]];

            foreach (array_values($items) as $position => $item) {
                $item->forceFill(['sort_order' => $position + 1])->save();
            }

            $this->activityLogger->log('survey.question_moved', $user, $survey->project, $survey, [
                'survey_id' => $survey->getKey(),
                'question_id' => $question->getKey(),
                'direction' => $direction,
            ], $request);
        });
    }

    /**
     * @param  array<int, string>  $pageIds
     */
    public function reorderPages(User $user, Survey $survey, array $pageIds, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('update', $survey);
        $this->ensureCanReorder($survey);

        DB::transaction(function () use ($survey, $pageIds, $user, $request): void {
            $orderedIds = collect($pageIds)->filter()->values();
            $pages = $survey->pages()->whereIn('id', $orderedIds->all())->get()->keyBy('id');

            if ($orderedIds->count() !== $pages->count()) {
                throw ValidationException::withMessages([
                    'page_order' => 'Page order contains invalid sections for this survey.',
                ]);
            }

            $remaining = $survey->pages()
                ->whereNotIn('id', $orderedIds->all())
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get()
                ->pluck('id');

            $orderedIds = $orderedIds->merge($remaining)->values();
            $pages = $survey->pages()->whereIn('id', $orderedIds->all())->get()->keyBy('id');

            foreach ($orderedIds as $index => $pageId) {
                $pages->get($pageId)?->forceFill(['sort_order' => $index + 1])->save();
            }

            $this->renumberQuestions($survey);

            $this->activityLogger->log('survey.pages_reordered', $user, $survey->project, $survey, [
                'survey_id' => $survey->getKey(),
                'page_ids' => $orderedIds->all(),
            ], $request);
        });
    }

    public function movePage(User $user, SurveyPage $page, string $direction, ?Request $request = null): void
    {
        Gate::forUser($user)->authorize('update', $page->survey);
        $this->ensureCanReorder($page->survey);

        DB::transaction(function () use ($page, $direction, $user, $request): void {
            $survey = $page->survey;
            $pages = $survey->pages()->orderBy('sort_order')->orderBy('created_at')->get()->values();
            $index = $pages->search(fn (SurveyPage $candidate): bool => $candidate->is($page));

            if ($index === false) {
                return;
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($targetIndex < 0 || $targetIndex >= $pages->count()) {
                return;
            }

            $items = $pages->all();
            [$items[$index], $items[$targetIndex]] = [$items[$targetIndex], $items[$index]];

            foreach (array_values($items) as $position => $item) {
                $item->forceFill(['sort_order' => $position + 1])->save();
            }

            $this->renumberQuestions($survey);

            $this->activityLogger->log('survey.page_moved', $user, $survey->project, $survey, [
                'survey_id' => $survey->getKey(),
                'page_id' => $page->getKey(),
                'direction' => $direction,
            ], $request);
        });
    }

    private function ensureCanReorder(Survey $survey): void
    {
        if ($survey->responses()->official()->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Reorder is blocked because this survey already has real responses. Pilot/test responses do not block reorder.',
            ]);
        }
    }

    private function targetPage(Survey $survey, ?string $pageId): ?SurveyPage
    {
        if (blank($pageId)) {
            return null;
        }

        $page = $survey->pages()->whereKey($pageId)->first();

        if (! $page instanceof SurveyPage) {
            throw ValidationException::withMessages([
                'page_id' => 'Selected page does not belong to this survey.',
            ]);
        }

        return $page;
    }

    /**
     * @param  Collection<int, string>|null  $preferredIds
     */
    private function renumberQuestions(Survey $survey, ?Collection $preferredIds = null, ?SurveyPage $targetPage = null): void
    {
        $preferredIds ??= collect();
        $ordered = collect();

        $pages = $survey->pages()->orderBy('sort_order')->orderBy('created_at')->get();
        foreach ($pages as $page) {
            $pageQuestions = $survey->questions()
                ->where('page_id', $page->getKey())
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get();

            if ($targetPage?->is($page)) {
                $preferred = $preferredIds
                    ->map(fn (string $id): ?SurveyQuestion => $pageQuestions->firstWhere('id', $id))
                    ->filter();
                $remaining = $pageQuestions->reject(fn (SurveyQuestion $question): bool => $preferredIds->contains($question->getKey()));
                $pageQuestions = $preferred->merge($remaining)->values();
            }

            $ordered = $ordered->merge($pageQuestions);
        }

        $unpaged = $survey->questions()
            ->whereNull('page_id')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        if (! $targetPage && $preferredIds->isNotEmpty()) {
            $all = $ordered->merge($unpaged);
            $preferred = $preferredIds
                ->map(fn (string $id): ?SurveyQuestion => $all->firstWhere('id', $id))
                ->filter();
            $remaining = $all->reject(fn (SurveyQuestion $question): bool => $preferredIds->contains($question->getKey()));
            $ordered = $preferred->merge($remaining)->values();
        } else {
            $ordered = $ordered->merge($unpaged);
        }

        foreach ($ordered->values() as $index => $question) {
            $question->forceFill(['sort_order' => $index + 1])->save();
        }
    }
}
