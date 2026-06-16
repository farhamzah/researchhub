@php
    use App\Models\SurveyDistributionBatch;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Distribution Package - {{ $survey->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 32px; line-height: 1.5; }
        h1, h2, h3 { margin: 0; }
        h1 { font-size: 24px; }
        h2 { border-bottom: 1px solid #cbd5e1; font-size: 18px; margin-top: 28px; padding-bottom: 6px; }
        h3 { font-size: 14px; margin-top: 14px; }
        p, li, td, th { font-size: 12px; }
        .muted { color: #64748b; }
        .box { border: 1px solid #cbd5e1; border-radius: 6px; margin-top: 12px; padding: 12px; }
        table { border-collapse: collapse; margin-top: 10px; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
        textarea { border: 1px solid #cbd5e1; font-family: Arial, sans-serif; font-size: 12px; min-height: 100px; padding: 8px; width: 100%; }
        @media print { .no-print { display: none; } body { margin: 18mm; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print Package</button>

    <h1>Research Distribution Package</h1>
    <p class="muted">Generated {{ $generatedAt->format('Y-m-d H:i') }} for {{ $survey->title }}</p>
    <p><strong>Project:</strong> {{ $survey->project?->title ?? 'Not assigned' }}</p>

    <h2>Distribution Overview</h2>
    <table>
        <thead>
            <tr>
                <th>Area</th>
                <th>Status</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($distribution['overview'] as $card)
                <tr>
                    <td>{{ $card['label'] }}</td>
                    <td>{{ $card['status'] }}</td>
                    <td>{{ $card['value'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Instrument Links</h2>
    <table>
        <thead>
            <tr>
                <th>Audience</th>
                <th>Instrument</th>
                <th>Public link</th>
                <th>Intro</th>
                <th>Responses</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($distribution['instruments'] as $panel)
                <tr>
                    <td>{{ $panel['label'] }}</td>
                    <td>{{ $panel['survey']?->title ?? 'Missing' }}</td>
                    <td>{{ $panel['link'] ?? 'Not available' }}</td>
                    <td>{{ $panel['intro_complete'] ? 'Complete' : 'Incomplete' }}; consent {{ $panel['consent_required'] ? 'required' : 'not required' }}</td>
                    <td>{{ $panel['response_count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Validation Summary</h2>
    @forelse ($distribution['validation']['rounds'] as $roundPanel)
        <div class="box">
            <h3>{{ $roundPanel['round']->title }}</h3>
            <p>Report: {{ $roundPanel['report_route'] }}</p>
            <table>
                <thead><tr><th>Validator</th><th>Email</th><th>Status</th><th>Token note</th></tr></thead>
                <tbody>
                    @forelse ($roundPanel['assignments'] as $assignmentPanel)
                        <tr>
                            <td>{{ $assignmentPanel['name'] }}</td>
                            <td>{{ $assignmentPanel['email'] ?: 'N/A' }}</td>
                            <td>{{ $assignmentPanel['status_label'] }}</td>
                            <td>{{ $assignmentPanel['has_token'] ? $distribution['tokenSafetyNotice'] : 'No link generated yet.' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No validators assigned.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p>No validation rounds yet.</p>
    @endforelse

    <h2>Readability Summary</h2>
    @forelse ($distribution['readability']['rounds'] as $roundPanel)
        <div class="box">
            <h3>{{ $roundPanel['round']->title }}</h3>
            <p>Report: {{ $roundPanel['report_route'] }}</p>
            <table>
                <thead><tr><th>Participant</th><th>Email</th><th>Status</th><th>Token note</th></tr></thead>
                <tbody>
                    @forelse ($roundPanel['participants'] as $participantPanel)
                        <tr>
                            <td>{{ $participantPanel['name'] }}</td>
                            <td>{{ $participantPanel['email'] ?: 'N/A' }}</td>
                            <td>{{ $participantPanel['status_label'] }}</td>
                            <td>{{ $participantPanel['has_token'] ? $distribution['tokenSafetyNotice'] : 'No link generated yet.' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No readability participants.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p>No readability rounds yet.</p>
    @endforelse

    <h2>Message Templates</h2>
    @foreach ($distribution['instruments'] as $panel)
        <div class="box">
            <h3>{{ $panel['label'] }}</h3>
            <p><strong>WhatsApp</strong></p>
            <textarea readonly>{{ $panel['whatsapp_message'] }}</textarea>
            <p><strong>Email</strong></p>
            <textarea readonly>{{ $panel['email_message'] }}</textarea>
        </div>
    @endforeach

    <h2>Supervisor Package</h2>
    <textarea readonly>{{ $distribution['supervisor']['message'] }}</textarea>

    <h2>Manual Tracking</h2>
    <table>
        <thead>
            <tr>
                <th>Audience</th>
                <th>Status</th>
                <th>Deadline</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($distribution['audienceLabels'] as $audience => $label)
                @php $batch = $distribution['batches'][$audience] ?? null; @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ $batch['status_label'] ?? 'Not tracked' }}</td>
                    <td>{{ isset($batch['deadline']) && $batch['deadline'] ? $batch['deadline']->toDateString() : 'N/A' }}</td>
                    <td>{{ $batch['notes'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted">Security note: Raw tokens are not stored for validation and readability links. Regenerate a link when a fresh copyable URL is needed.</p>
</body>
</html>
