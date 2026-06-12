<?php

namespace App\Http\Controllers;

use App\Models\SupervisionFeedback;
use App\Modules\Supervision\Actions\SubmitSupervisionFeedbackAction;
use App\Modules\Supervision\Services\SupervisionReviewTokenResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSupervisionReviewController extends Controller
{
    public function show(string $token, Request $request, SupervisionReviewTokenResolver $resolver): View|Response
    {
        $reviewLink = $resolver->resolve($token, $request, true);

        if (! $reviewLink) {
            abort(404);
        }

        if ($reviewLink->isSubmitted()) {
            return view('supervision.thank-you', ['reviewLink' => $reviewLink]);
        }

        if (! $reviewLink->isAccessible()) {
            return response()->view('supervision.unavailable', [
                'reviewLink' => $reviewLink,
            ], 403);
        }

        return view('supervision.show', [
            'reviewLink' => $reviewLink,
            'session' => $reviewLink->session,
            'project' => $reviewLink->session->project,
            'token' => $token,
            'decisionLabels' => SupervisionFeedback::DECISION_LABELS,
        ]);
    }

    public function store(
        string $token,
        Request $request,
        SupervisionReviewTokenResolver $resolver,
        SubmitSupervisionFeedbackAction $submitFeedback,
    ): View|RedirectResponse|Response {
        $reviewLink = $resolver->resolve($token, $request, false);

        if (! $reviewLink) {
            abort(404);
        }

        if ($reviewLink->isSubmitted()) {
            return view('supervision.thank-you', ['reviewLink' => $reviewLink]);
        }

        if (! $reviewLink->isAccessible()) {
            return response()->view('supervision.unavailable', [
                'reviewLink' => $reviewLink,
            ], 403);
        }

        $data = $request->validate([
            'decision' => ['required', 'string', Rule::in(SupervisionFeedback::DECISIONS)],
            'general_feedback' => ['required', 'string', 'max:10000'],
            'revision_notes' => ['nullable', 'string', 'max:10000'],
            'recommended_next_steps' => ['nullable', 'string', 'max:10000'],
            'supervisor_note' => ['nullable', 'string', 'max:10000'],
        ]);

        $submitFeedback->handle($reviewLink, $data, $request);

        return redirect()->route('supervision.review.show', ['token' => $token]);
    }
}
