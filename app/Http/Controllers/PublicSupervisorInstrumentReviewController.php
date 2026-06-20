<?php

namespace App\Http\Controllers;

use App\Models\SurveyQuestion;
use App\Models\SurveySupervisorReviewComment;
use App\Models\SurveySupervisorReviewer;
use App\Modules\SupervisorReviews\Actions\SubmitSupervisorInstrumentReviewAction;
use App\Modules\SupervisorReviews\Services\SupervisorReviewTokenResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicSupervisorInstrumentReviewController extends Controller
{
    public function show(string $token, Request $request, SupervisorReviewTokenResolver $resolver): View|Response
    {
        $reviewer = $resolver->resolve($token, $request, true);

        if (! $reviewer) {
            abort(404);
        }

        if ($reviewer->isSubmitted()) {
            return view('supervisor-review.thank-you', ['reviewer' => $reviewer]);
        }

        if (! $reviewer->isAccessible()) {
            return response()->view('supervisor-review.unavailable', [
                'reviewer' => $reviewer,
            ], 403);
        }

        return view('supervisor-review.show', $this->viewData($reviewer, $token));
    }

    public function store(
        string $token,
        Request $request,
        SupervisorReviewTokenResolver $resolver,
        SubmitSupervisorInstrumentReviewAction $submitReview,
    ): View|RedirectResponse|Response {
        $reviewer = $resolver->resolve($token, $request, false);

        if (! $reviewer) {
            abort(404);
        }

        if ($reviewer->isSubmitted()) {
            return view('supervisor-review.thank-you', ['reviewer' => $reviewer]);
        }

        if (! $reviewer->isAccessible()) {
            return response()->view('supervisor-review.unavailable', [
                'reviewer' => $reviewer,
            ], 403);
        }

        $data = $request->validate([
            'final_decision' => ['required', 'string', Rule::in(SurveySupervisorReviewer::DECISIONS)],
            'final_notes' => ['nullable', 'string', 'max:10000'],
            'comments' => ['nullable', 'array'],
            'comments.*.comment_type' => ['required_with:comments', 'string', Rule::in(SurveySupervisorReviewComment::TYPES)],
            'comments.*.survey_question_id' => ['nullable', 'string', Rule::exists('survey_questions', 'id')],
            'comments.*.target_key' => ['nullable', 'string', 'max:255'],
            'comments.*.target_label' => ['nullable', 'string', 'max:500'],
            'comments.*.comment' => ['nullable', 'string', 'max:10000'],
            'comments.*.suggested_revision' => ['nullable', 'string', 'max:10000'],
            'comments.*.severity' => ['nullable', 'string', Rule::in(SurveySupervisorReviewComment::SEVERITIES)],
            'comments.*.decision' => ['nullable', 'string', Rule::in(SurveySupervisorReviewComment::DECISIONS)],
        ]);

        try {
            $submitReview->handle($reviewer, [
                'final_decision' => $data['final_decision'],
                'final_notes' => $data['final_notes'] ?? null,
                'comments' => $this->normalizeComments($data['comments'] ?? []),
            ], $request);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()->route('supervisor-review.survey.show', ['token' => $token])
            ->with('status', 'supervisor-review-submitted');
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(SurveySupervisorReviewer $reviewer, string $token): array
    {
        $survey = $reviewer->round->survey;
        $survey->loadMissing('pages.questions.scoring.indicator');

        return [
            'reviewer' => $reviewer,
            'round' => $reviewer->round,
            'survey' => $survey,
            'project' => $survey->project,
            'pages' => $survey->pages,
            'questionsWithoutPage' => $survey->questions()
                ->whereNull('page_id')
                ->where('type', '!=', SurveyQuestion::TYPE_HIDDEN)
                ->orderBy('sort_order')
                ->get(),
            'token' => $token,
            'decisions' => SurveySupervisorReviewer::DECISION_LABELS,
            'itemDecisions' => SurveySupervisorReviewComment::DECISIONS,
            'severities' => SurveySupervisorReviewComment::SEVERITIES,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $comments
     * @return array<int, array<string, mixed>>
     */
    private function normalizeComments(array $comments): array
    {
        return collect($comments)
            ->filter(fn (mixed $comment): bool => is_array($comment) && filled($comment['comment'] ?? null))
            ->map(function (array $comment): array {
                $type = $comment['comment_type'];

                return [
                    'comment_type' => $type,
                    'survey_question_id' => $comment['survey_question_id'] ?? null,
                    'target_key' => $comment['target_key'] ?? null,
                    'target_label' => $comment['target_label'] ?? null,
                    'comment' => $comment['comment'],
                    'suggested_revision' => $comment['suggested_revision'] ?? null,
                    'severity' => $type === SurveySupervisorReviewComment::TYPE_ITEM ? ($comment['severity'] ?? SurveySupervisorReviewComment::SEVERITY_MODERATE) : ($comment['severity'] ?? null),
                    'decision' => $type === SurveySupervisorReviewComment::TYPE_ITEM ? ($comment['decision'] ?? SurveySupervisorReviewComment::DECISION_REVISE) : ($comment['decision'] ?? null),
                ];
            })
            ->values()
            ->all();
    }
}
