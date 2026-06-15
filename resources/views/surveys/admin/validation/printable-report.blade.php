@php
    $format = fn ($value, int $decimals = 2): string => $value === null ? 'N/A' : number_format((float) $value, $decimals);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Printable Expert Validation Report - MyRiset</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; margin: 32px; }
        h1, h2, h3 { margin: 0; }
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
        <p class="label">MyRiset Expert Validator Assessment</p>
        <h1>{{ $survey->title }}</h1>
        <p class="meta">{{ $survey->project?->title ?? 'No project' }} | {{ $round->title }} | Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>
    </header>

    <section class="grid">
        <div class="box">
            <p class="label">Validators</p>
            <p class="value">{{ $result->summary['submitted_count'] }} / {{ $result->summary['assigned_count'] }}</p>
        </div>
        <div class="box">
            <p class="label">Average Score</p>
            <p class="value">{{ $format($result->summary['overall_average_score']) }}</p>
        </div>
        <div class="box">
            <p class="label">Feasibility</p>
            <p class="value">{{ $format($result->summary['percentage_feasibility']) }}%</p>
        </div>
        <div class="box">
            <p class="label">Category</p>
            <p class="value">{{ $result->summary['validation_category'] }}</p>
        </div>
    </section>

    <h2>Validator List</h2>
    <table>
        <thead>
            <tr>
                <th>Validator</th>
                <th>Role</th>
                <th>Status</th>
                <th>Average</th>
                <th>Decision</th>
                <th>Submitted</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($result->validators as $validator)
                <tr>
                    <td>{{ $validator['validator_name'] }}</td>
                    <td>{{ \App\Models\ExpertValidatorProject::ROLE_LABELS[$validator['role']] ?? ($validator['role'] ?: 'Validator') }}</td>
                    <td>{{ \App\Models\SurveyValidationAssignment::STATUS_LABELS[$validator['status']] ?? $validator['status'] }}</td>
                    <td>{{ $format($validator['average_score']) }}</td>
                    <td>{{ $validator['feasibility_decision'] ? (\App\Models\SurveyValidationRecommendation::DECISION_LABELS[$validator['feasibility_decision']] ?? $validator['feasibility_decision']) : 'Not submitted' }}</td>
                    <td>{{ $validator['submitted_at']?->format('Y-m-d H:i') ?? 'Not submitted' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Aspect Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Aspect</th>
                <th>Average Score</th>
                <th>Aiken's V</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($result->aspectSummary as $aspect)
                <tr>
                    <td>{{ $aspect['label'] }}</td>
                    <td>{{ $format($aspect['average_score']) }}</td>
                    <td>{{ $format($aspect['aiken_v'], 3) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Revision Matrix</h2>
    <table>
        <thead>
            <tr>
                <th>Validator</th>
                <th>Validator Comment</th>
                <th>Suggested / Researcher Action</th>
                <th>Status</th>
                <th>Researcher Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result->revisionMatrix as $revision)
                <tr>
                    <td>{{ $revision['validator_name'] }}</td>
                    <td>{{ $revision['validator_comment'] }}</td>
                    <td>{{ $revision['revision_action'] ?: 'Pending researcher action' }}</td>
                    <td>{{ \App\Models\SurveyValidationRevision::STATUS_LABELS[$revision['status']] ?? $revision['status'] }}</td>
                    <td>{{ $revision['researcher_note'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No revision suggestions submitted yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Copy-Ready Narrative</h2>
    <p>{{ $result->narrative }}</p>
</body>
</html>
