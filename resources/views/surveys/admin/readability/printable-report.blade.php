@php
    $format = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 2);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Printable Readability Report - MyRiset</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; margin: 32px; }
        h1, h2 { margin: 0; }
        h1 { font-size: 22px; }
        h2 { border-bottom: 1px solid #d1d5db; font-size: 16px; margin-top: 28px; padding-bottom: 6px; }
        table { border-collapse: collapse; margin-top: 12px; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        .meta { color: #4b5563; margin-top: 4px; }
        .grid { display: grid; gap: 10px; grid-template-columns: repeat(4, 1fr); margin-top: 16px; }
        .box { border: 1px solid #d1d5db; padding: 10px; }
        .label { color: #6b7280; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .value { font-size: 16px; font-weight: bold; margin-top: 4px; }
        @media print {
            body { margin: 18mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print Report</button>

    <header>
        <p class="label">MyRiset Readability Test Report</p>
        <h1>{{ $survey->title }}</h1>
        <p class="meta">{{ $survey->project?->title ?? 'No project' }} | {{ $round->title }} | Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>
    </header>

    <section class="grid">
        <div class="box">
            <p class="label">Participants</p>
            <p class="value">{{ $result['summary']['submitted_count'] }} / {{ $result['summary']['participant_count'] }}</p>
        </div>
        <div class="box">
            <p class="label">Average Score</p>
            <p class="value">{{ $format($result['summary']['average_readability_score']) }}</p>
        </div>
        <div class="box">
            <p class="label">Category</p>
            <p class="value">{{ $result['summary']['category'] }}</p>
        </div>
        <div class="box">
            <p class="label">Confusing Items</p>
            <p class="value">{{ $result['summary']['confusing_item_count'] }}</p>
        </div>
    </section>

    <h2>Issue Type Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Issue Type</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result['issue_type_counts'] as $issue => $count)
                <tr>
                    <td>{{ \App\Models\SurveyReadabilityQuestionFeedback::ISSUE_LABELS[$issue] ?? $issue }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No issue types submitted yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Confusing Item List</h2>
    <table>
        <thead>
            <tr>
                <th>Question</th>
                <th>Flags</th>
                <th>Comments</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result['flagged_questions'] as $question)
                <tr>
                    <td>{{ $question['question_text'] }}</td>
                    <td>{{ $question['count'] }}</td>
                    <td>{{ implode(' | ', $question['comments']) ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No confusing questions submitted yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Revision Matrix</h2>
    <table>
        <thead>
            <tr>
                <th>Question Number</th>
                <th>Question Text</th>
                <th>Issue Summary</th>
                <th>Suggested Revision / Researcher Action</th>
                <th>Status</th>
                <th>Researcher Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result['revision_matrix'] as $revision)
                <tr>
                    <td>{{ $revision['question_number'] ?: '-' }}</td>
                    <td>{{ $revision['question_text'] ?: 'Overall instrument' }}</td>
                    <td>{{ $revision['issue_summary'] }}</td>
                    <td>{{ $revision['revision_action'] ?: 'Pending researcher action' }}</td>
                    <td>{{ \App\Models\SurveyReadabilityRevision::STATUS_LABELS[$revision['status']] ?? $revision['status'] }}</td>
                    <td>{{ $revision['researcher_note'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No revision suggestions submitted yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Copy-Ready Narrative</h2>
    <p>{{ $result['narrative'] }}</p>
</body>
</html>
