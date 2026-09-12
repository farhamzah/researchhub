<?php

namespace App\Modules\SupervisorReviews\Services;

use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewRound;
use Illuminate\Support\Collection;

class SupervisorReviewReportService
{
    /** @return array<string, mixed> */
    public function build(SurveySupervisorReviewRound $round): array
    {
        $round->loadMissing(['survey.project', 'reviewers.comments']);
        $snapshot = $round->snapshot_json ?? [];
        $questions = $this->snapshotQuestions($snapshot);
        $reviewers = $round->reviewers->map(function (SurveySupervisorReviewer $reviewer) use ($questions, $round): array {
            $itemComments = $reviewer->comments->where('comment_type', SurveySupervisorReviewComment::TYPE_ITEM)->keyBy('survey_question_id');
            $counts = collect(SurveySupervisorReviewComment::V2_DECISIONS)
                ->mapWithKeys(fn (string $decision): array => [$decision => $itemComments->where('decision', $decision)->count()])
                ->all();
            $rows = $questions->map(function (array $question) use ($itemComments): array {
                $comment = $itemComments->get($question['id']);

                return [
                    'code' => $question['question_key'] ?? '—',
                    'reviewed_wording' => $question['label'] ?? '—',
                    'answer_options' => $this->answerOptions($question),
                    'decision' => SurveySupervisorReviewComment::V2_DECISION_LABELS[$comment?->decision] ?? '—',
                    'comment' => $comment && $comment->comment !== '—' ? $comment->comment : '—',
                    'revised_wording' => $comment?->suggested_revision ?: '—',
                ];
            })->values()->all();
            $decision = SurveySupervisorReviewer::V2_DECISION_LABELS[$reviewer->final_decision] ?? 'Belum diputuskan';
            $date = $reviewer->submitted_at?->timezone(config('app.timezone'))->format('d F Y H:i') ?? 'Belum disubmit';
            $narrative = sprintf(
                'Instrumen %s versi %s direview oleh %s pada %s. Keputusan item: %d Pertahankan, %d Revisi, %d Hapus, dan %d Diskusikan. Keputusan umum: %s. Komentar umum: %s',
                $round->survey->title,
                $round->instrument_version ?: ($round->survey->instrument_version ?: '—'),
                $reviewer->supervisor_name,
                $date,
                $counts[SurveySupervisorReviewComment::DECISION_KEEP],
                $counts[SurveySupervisorReviewComment::DECISION_REVISE],
                $counts[SurveySupervisorReviewComment::DECISION_REMOVE],
                $counts[SurveySupervisorReviewComment::DECISION_DISCUSS],
                $decision,
                $reviewer->final_notes ?: 'Tidak ada komentar umum.'
            );

            return [
                'name' => $reviewer->supervisor_name,
                'code' => $reviewer->supervisor_code,
                'submitted_at' => $reviewer->submitted_at,
                'decision' => $decision,
                'general_comment' => $reviewer->final_notes ?: '—',
                'counts' => $counts,
                'narrative' => $narrative,
                'rows' => $rows,
                'table_tsv' => $this->tableTsv($rows),
            ];
        })->values()->all();

        return [
            'title' => 'Laporan Review Pembimbing Instrumen',
            'instrument' => $snapshot['survey']['title'] ?? $round->survey->title,
            'identifier' => $snapshot['survey']['instrument_identifier'] ?? $round->survey->instrument_identifier,
            'version' => $round->instrument_version ?: ($snapshot['survey']['instrument_version'] ?? $round->survey->instrument_version),
            'project' => $round->survey->project?->title,
            'round' => $round->title,
            'snapshot_taken_at' => $round->snapshot_taken_at,
            'reviewers' => $reviewers,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function snapshotQuestions(array $snapshot): Collection
    {
        return collect($snapshot['pages'] ?? [])->flatMap(fn (array $page): array => $page['questions'] ?? [])
            ->merge($snapshot['questions_without_page'] ?? [])->values();
    }

    private function answerOptions(array $question): string
    {
        $choices = $question['options']['choices'] ?? $question['options'] ?? [];
        if ($choices === []) {
            $choices = $question['settings']['scale_labels'] ?? [];
        }
        if (is_array($choices) && ! array_is_list($choices)) {
            return collect($choices)->map(fn (mixed $label, mixed $value): string => $value.' — '.$label)->implode('; ');
        }

        return collect(is_array($choices) ? $choices : [])->map(function (mixed $choice): string {
            if (is_array($choice)) {
                return (string) ($choice['label'] ?? $choice['value'] ?? json_encode($choice));
            }

            return (string) $choice;
        })->implode('; ') ?: 'Jawaban teks bebas';
    }

    private function tableTsv(array $rows): string
    {
        $lines = [implode("\t", ['Kode', 'Redaksi saat direview', 'Opsi jawaban', 'Keputusan', 'Komentar', 'Redaksi revisi'])];
        foreach ($rows as $row) {
            $lines[] = implode("\t", array_map(fn (mixed $value): string => str_replace(["\t", "\r", "\n"], ' ', (string) $value), array_values($row)));
        }

        return implode("\n", $lines);
    }
}
