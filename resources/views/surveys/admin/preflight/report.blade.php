@php
    $statusClass = fn (string $status): string => match ($status) {
        'failed' => 'fail',
        'warning' => 'warn',
        'passed' => 'pass',
        'info' => 'info',
        default => 'info',
    };

    $sourceLabel = fn (string $source): string => str($source)->replace('_', ' ')->title()->toString();
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pre-Distribution QA Report - MyRiset</title>
    <style>
        body { color: #0f172a; font-family: Arial, sans-serif; margin: 32px; }
        h1, h2, h3 { margin-bottom: 8px; }
        p { line-height: 1.5; }
        table { border-collapse: collapse; margin-top: 16px; width: 100%; }
        th, td { border: 1px solid #cbd5e1; font-size: 12px; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        .meta { color: #475569; font-size: 13px; }
        .badge { border-radius: 999px; display: inline-block; font-size: 12px; font-weight: 700; padding: 4px 10px; }
        .pass { background: #dcfce7; color: #166534; }
        .warn { background: #fef3c7; color: #92400e; }
        .fail { background: #fee2e2; color: #991b1b; }
        .info { background: #e0f2fe; color: #075985; }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(4, 1fr); margin: 20px 0; }
        .card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; }
        .signatures { display: grid; gap: 40px; grid-template-columns: repeat(2, 1fr); margin-top: 48px; }
        .line { border-top: 1px solid #0f172a; margin-top: 72px; padding-top: 8px; }
        @media print { button { display: none; } body { margin: 18mm; } }
    </style>
</head>
<body>
    <button type="button" onclick="window.print()">Print</button>

    <header>
        <p class="meta">MyRiset Analysis</p>
        <h1>Pre-Distribution QA Report</h1>
        <p><strong>{{ $survey->title }}</strong></p>
        <p class="meta">{{ $survey->project?->title ?? 'No project' }} | Generated {{ $qa['generated_at']->format('d M Y H:i') }}</p>
        <p><span class="badge {{ $qa['summary']['critical_failed'] > 0 ? 'fail' : ($qa['summary']['warnings'] > 0 ? 'warn' : 'pass') }}">{{ $qa['overall_status'] }}</span></p>
    </header>

    <section class="grid">
        <div class="card"><strong>Total Checks</strong><br>{{ $qa['summary']['total'] }}</div>
        <div class="card"><strong>Passed</strong><br>{{ $qa['summary']['passed'] }}</div>
        <div class="card"><strong>Warnings</strong><br>{{ $qa['summary']['warnings'] }}</div>
        <div class="card"><strong>Critical Failed</strong><br>{{ $qa['summary']['critical_failed'] }}</div>
    </section>

    <section>
        <h2>Executive Summary</h2>
        <p>This report records deterministic pre-distribution checks for instrument completeness, public link readiness, validation, readability, distribution, collection monitoring, analysis package, and ADDIE synthesis readiness. It does not send links or expose respondent identity.</p>
        @if ($qa['latest_review'])
            <p class="meta">Latest ready review: {{ $qa['latest_review']->reviewed_at?->format('d M Y H:i') ?? '-' }} by {{ $qa['latest_review']->reviewer?->name ?? 'Unknown reviewer' }}</p>
        @endif
    </section>

    @if ($qa['critical_issues'])
        <section>
            <h2>Critical Issues</h2>
            <ul>
                @foreach ($qa['critical_issues'] as $check)
                    <li><strong>{{ $check['label'] }}</strong>: {{ $check['message'] }} Recommendation: {{ $check['recommendation'] }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($qa['warnings'])
        <section>
            <h2>Warning Items</h2>
            <ul>
                @foreach ($qa['warnings'] as $check)
                    <li><strong>{{ $check['label'] }}</strong>: {{ $check['message'] }} Recommendation: {{ $check['recommendation'] }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @foreach ($qa['grouped_checks'] as $source => $checks)
        <section>
            <h2>{{ $sourceLabel($source) }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($checks as $check)
                        <tr>
                            <td>{{ $check['label'] }}</td>
                            <td>{{ $check['severity'] }}</td>
                            <td><span class="badge {{ $statusClass($check['status']) }}">{{ $check['status'] }}</span></td>
                            <td>{{ $check['message'] }}</td>
                            <td>{{ $check['recommendation'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    <section class="signatures">
        <div>
            <p class="line">Researcher</p>
        </div>
        <div>
            <p class="line">Supervisor / Promoter</p>
        </div>
    </section>
</body>
</html>
