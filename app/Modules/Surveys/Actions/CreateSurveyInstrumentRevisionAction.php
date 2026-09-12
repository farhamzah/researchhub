<?php

namespace App\Modules\Surveys\Actions;

use App\Models\Survey;
use App\Models\SurveyWorkflowTransition;
use App\Models\User;
use App\Modules\Surveys\Services\SurveyInstrumentWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateSurveyInstrumentRevisionAction
{
    public function handle(Survey $source, User $actor): Survey
    {
        if ($source->workflow_state !== SurveyInstrumentWorkflowService::SUPERVISOR_REVISION_REQUIRED) {
            throw new RuntimeException('Versi baru hanya dapat dibuat setelah keputusan Perlu Revisi.');
        }

        $source->loadMissing(['pages.questions', 'scales.indicators.questionScorings']);

        return DB::transaction(function () use ($source, $actor): Survey {
            $version = $this->nextVersion((string) $source->instrument_version);
            $copy = $source->replicate([
                'slug', 'status', 'instrument_version', 'workflow_state', 'supersedes_survey_id',
                'is_public', 'published_at', 'closed_at', 'created_at', 'updated_at', 'deleted_at',
            ]);
            $copy->forceFill([
                'title' => preg_replace('/\s+v\d+(?:\.\d+)*$/i', '', $source->title).' v'.$version,
                'slug' => $this->uniqueSlug($source, $version),
                'status' => Survey::STATUS_DRAFT,
                'instrument_version' => $version,
                'workflow_state' => SurveyInstrumentWorkflowService::DRAFT,
                'supersedes_survey_id' => $source->getKey(),
                'is_public' => false,
                'published_at' => null,
                'closed_at' => null,
                'created_by' => $actor->getKey(),
            ])->save();

            $pageMap = [];
            $questionMap = [];
            foreach ($source->pages as $page) {
                $pageCopy = $page->replicate(['survey_id', 'created_at', 'updated_at']);
                $pageCopy->survey_id = $copy->getKey();
                $pageCopy->save();
                $pageMap[$page->getKey()] = $pageCopy->getKey();

                foreach ($page->questions as $question) {
                    $questionCopy = $question->replicate(['survey_id', 'page_id', 'created_at', 'updated_at']);
                    $questionCopy->survey_id = $copy->getKey();
                    $questionCopy->page_id = $pageCopy->getKey();
                    $questionCopy->save();
                    $questionMap[$question->getKey()] = $questionCopy->getKey();
                }
            }

            $indicatorMap = [];
            foreach ($source->scales as $scale) {
                $scaleCopy = $scale->replicate(['survey_id', 'created_at', 'updated_at', 'deleted_at']);
                $scaleCopy->survey_id = $copy->getKey();
                $scaleCopy->save();

                foreach ($scale->indicators as $indicator) {
                    $indicatorCopy = $indicator->replicate(['survey_id', 'survey_scale_id', 'created_at', 'updated_at', 'deleted_at']);
                    $indicatorCopy->survey_id = $copy->getKey();
                    $indicatorCopy->survey_scale_id = $scaleCopy->getKey();
                    $indicatorCopy->save();
                    $indicatorMap[$indicator->getKey()] = $indicatorCopy->getKey();
                }
            }

            foreach ($source->questionScorings()->get() as $scoring) {
                if (! isset($questionMap[$scoring->survey_question_id], $indicatorMap[$scoring->survey_indicator_id])) {
                    continue;
                }
                $scoringCopy = $scoring->replicate(['survey_id', 'survey_question_id', 'survey_indicator_id', 'created_at', 'updated_at']);
                $scoringCopy->survey_id = $copy->getKey();
                $scoringCopy->survey_question_id = $questionMap[$scoring->survey_question_id];
                $scoringCopy->survey_indicator_id = $indicatorMap[$scoring->survey_indicator_id];
                $scoringCopy->save();
            }

            SurveyWorkflowTransition::create([
                'survey_id' => $copy->getKey(),
                'actor_id' => $actor->getKey(),
                'actor_label' => $actor->name,
                'from_state' => null,
                'to_state' => SurveyInstrumentWorkflowService::DRAFT,
                'instrument_version' => $version,
                'comment' => 'Versi revisi dibuat dari '.$source->instrument_identifier.' tanpa menyalin respons.',
            ]);

            return $copy->fresh(['pages.questions']);
        });
    }

    private function nextVersion(string $version): string
    {
        if (! preg_match('/^(\d+)\.(\d+)$/', $version, $matches)) {
            throw new RuntimeException('Format versi instrumen tidak didukung.');
        }

        return $matches[1].'.'.((int) $matches[2] + 1);
    }

    private function uniqueSlug(Survey $source, string $version): string
    {
        $base = Str::slug($source->instrument_code.'-v'.$version);
        $slug = $base;
        $suffix = 2;
        while (Survey::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
