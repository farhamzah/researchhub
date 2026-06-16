@php
    use App\Models\AnalysisSynthesisItem;

    $package = $packageData['package'];
    $format = fn ($value): string => $value === null ? 'N/A' : number_format((float) $value, 2);
    $label = fn (?string $value): string => $value ? str($value)->replace('_', ' ')->title()->toString() : 'N/A';
    $sectionClass = 'analysis-package-section break-after-page border-b border-slate-200 py-8 print:border-b-0';
@endphp

<article class="mx-auto max-w-5xl bg-white text-slate-950">
    <section class="{{ $sectionClass }} min-h-[780px] text-center">
        <div class="flex min-h-[720px] flex-col items-center justify-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ $package->stage }}</p>
            <h1 class="mt-6 max-w-3xl text-4xl font-semibold leading-tight">{{ $package->title }}</h1>
            <p class="mt-6 text-xl font-semibold">{{ $survey->project?->title ?? $survey->title }}</p>
            <p class="mt-2 text-lg text-slate-700">{{ $survey->title }}</p>

            <dl class="mt-10 grid w-full max-w-2xl gap-3 text-left text-sm sm:grid-cols-2">
                <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs uppercase tracking-wide text-slate-500">Researcher</dt><dd class="mt-1 font-semibold">{{ $package->researcher_name ?: '-' }}</dd></div>
                <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs uppercase tracking-wide text-slate-500">Identifier</dt><dd class="mt-1 font-semibold">{{ $package->researcher_identifier ?: '-' }}</dd></div>
                <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs uppercase tracking-wide text-slate-500">Institution</dt><dd class="mt-1 font-semibold">{{ $package->institution ?: '-' }}</dd></div>
                <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs uppercase tracking-wide text-slate-500">Study Program</dt><dd class="mt-1 font-semibold">{{ $package->study_program ?: '-' }}</dd></div>
                <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs uppercase tracking-wide text-slate-500">Document Code</dt><dd class="mt-1 font-semibold">{{ $package->document_code ?: '-' }}</dd></div>
                <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs uppercase tracking-wide text-slate-500">Version / Date</dt><dd class="mt-1 font-semibold">{{ $package->version }} / {{ $package->document_date?->toFormattedDateString() ?? '-' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">1. Purpose of Analysis Package</h2>
        <p class="mt-4 text-sm leading-7 text-slate-700">{{ $package->purpose_text }}</p>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">2. Research and Development Context</h2>
        <div class="mt-4 space-y-3 text-sm leading-7 text-slate-700">
            <p>PharmVR dikembangkan sebagai modul pembelajaran Virtual Reality berbasis CPOB/GMP untuk mendukung pembelajaran farmasi industri. Tahap ADDIE Analysis mengumpulkan bukti kebutuhan dari mahasiswa, dosen, praktisi, validasi ahli, dan uji keterbacaan.</p>
            <p>Data pada paket ini digunakan untuk menyiapkan keputusan tahap Design, termasuk prioritas konten, fitur, scene VR, assessment, dan kebutuhan revisi instrumen.</p>
        </div>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">3. List of Analysis Instruments</h2>
        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Instrument</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Target</th>
                        <th class="px-4 py-3">Sections</th>
                        <th class="px-4 py-3">Questions</th>
                        <th class="px-4 py-3">Intro</th>
                        <th class="px-4 py-3">Responses</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($packageData['instrument_list'] as $item)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $item['name'] }}</td>
                            <td class="px-4 py-3">{{ $item['type'] }}</td>
                            <td class="px-4 py-3">{{ $item['target_respondent'] }}</td>
                            <td class="px-4 py-3">{{ $item['section_count'] }}</td>
                            <td class="px-4 py-3">{{ $item['question_count'] }}</td>
                            <td class="px-4 py-3">{{ $item['intro_status'] }}</td>
                            <td class="px-4 py-3">{{ $item['response_count'] }}</td>
                            <td class="px-4 py-3">{{ $item['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach ([
        'student' => '4. Student Questionnaire Instrument',
        'lecturer' => '5. Lecturer Questionnaire Instrument',
        'practitioner' => '6. Practitioner Interview Form',
    ] as $instrumentKey => $heading)
        @php $instrument = $packageData['instruments'][$instrumentKey]; @endphp
        <section class="{{ $sectionClass }}">
            <h2 class="text-2xl font-semibold">{{ $heading }}</h2>
            @if (! $instrument['exists'])
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">{{ $instrument['label'] }} has not been created yet.</p>
            @else
                @php $instrumentSurvey = $instrument['survey']; @endphp
                <div class="mt-4 space-y-3 text-sm leading-7 text-slate-700">
                    <p><strong>Survey title:</strong> {{ $instrumentSurvey->title }}</p>
                    <p><strong>Instrument type:</strong> {{ $instrument['type'] }}</p>
                    @if ($instrumentKey === 'practitioner')
                        <p class="rounded-md border border-blue-200 bg-blue-50 p-3 text-blue-950">Identitas narasumber dapat menggunakan inisial dan nama institusi/industri dapat dikosongkan jika bersifat rahasia.</p>
                    @endif
                    <p><strong>Intro:</strong> {{ $instrumentSurvey->intro_text ?: '-' }}</p>
                    <p><strong>Estimated duration:</strong> {{ $instrumentSurvey->estimated_duration ?: '-' }}</p>
                    <p><strong>Privacy statement:</strong> {{ $instrumentSurvey->privacy_statement ?: '-' }}</p>
                    <p><strong>Consent text:</strong> {{ $instrumentSurvey->consent_text ?: '-' }}</p>
                </div>

                <div class="mt-5 space-y-5">
                    @foreach ($instrument['sections'] as $section)
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h3 class="font-semibold">{{ $section['title'] }}</h3>
                            @if ($section['description'])
                                <p class="mt-1 text-sm text-slate-600">{{ $section['description'] }}</p>
                            @endif
                            <div class="mt-3 space-y-3">
                                @forelse ($section['questions'] as $question)
                                    <div class="rounded-md bg-slate-50 p-3 text-sm">
                                        <p class="font-semibold">{{ $loop->iteration }}. {{ $question['label'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $question['type'] }} / {{ $question['required'] ? 'Required' : 'Optional' }}</p>
                                        @if ($question['help_text'])
                                            <p class="mt-1 text-xs text-slate-600">{{ $question['help_text'] }}</p>
                                        @endif
                                        @if ($question['options'])
                                            <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Options</p>
                                            <ul class="mt-1 list-disc pl-5 text-xs text-slate-600">
                                                @foreach ($question['options'] as $option)
                                                    <li>{{ $option }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No visible questions.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">7. Expert Validation Summary</h2>
        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Assigned</dt><dd class="text-lg font-semibold">{{ $packageData['validation']['assigned_count'] }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Submitted</dt><dd class="text-lg font-semibold">{{ $packageData['validation']['submitted_count'] }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Category</dt><dd class="text-lg font-semibold">{{ $packageData['validation']['category'] }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Average Score</dt><dd class="text-lg font-semibold">{{ $format($packageData['validation']['average_score']) }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Feasibility</dt><dd class="text-lg font-semibold">{{ $format($packageData['validation']['percentage']) }}%</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Rounds</dt><dd class="text-lg font-semibold">{{ $packageData['validation']['round_count'] }}</dd></div>
        </dl>
        <h3 class="mt-5 font-semibold">Revision Notes Summary</h3>
        <ul class="mt-2 list-disc pl-5 text-sm text-slate-700">
            @forelse ($packageData['validation']['revision_suggestions'] as $note)
                <li>{{ $note }}</li>
            @empty
                <li>No submitted validation revision notes yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">8. Readability Test Summary</h2>
        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Participants</dt><dd class="text-lg font-semibold">{{ $packageData['readability']['participant_count'] }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Submitted</dt><dd class="text-lg font-semibold">{{ $packageData['readability']['submitted_count'] }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Category</dt><dd class="text-lg font-semibold">{{ $packageData['readability']['category'] }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Average Score</dt><dd class="text-lg font-semibold">{{ $format($packageData['readability']['average_score']) }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Confusing Items</dt><dd class="text-lg font-semibold">{{ $packageData['readability']['confusing_item_count'] }}</dd></div>
            <div class="rounded-md border border-slate-200 p-4"><dt class="text-xs text-slate-500">Target</dt><dd class="text-lg font-semibold">{{ $packageData['readability']['target_participants'] }}</dd></div>
        </dl>
        <h3 class="mt-5 font-semibold">Revision Recommendations</h3>
        <ul class="mt-2 list-disc pl-5 text-sm text-slate-700">
            @forelse ($packageData['readability']['revision_suggestions'] as $row)
                <li>{{ is_array($row) ? ($row['revision_suggestion'] ?? $row['comment'] ?? json_encode($row)) : $row }}</li>
            @empty
                <li>No readability revision recommendations yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">9. Distribution Summary</h2>
        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Audience</th>
                        <th class="px-4 py-3">Link Ready</th>
                        <th class="px-4 py-3">Sent</th>
                        <th class="px-4 py-3">Pending</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3">Revoked</th>
                        <th class="px-4 py-3">Deadline</th>
                        <th class="px-4 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($packageData['distribution_rows'] as $row)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $row['audience'] }}</td>
                            <td class="px-4 py-3">{{ $row['link_ready_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['sent_manually_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['pending_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['submitted_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['revoked_count'] }}</td>
                            <td class="px-4 py-3">{{ $row['deadline']?->toFormattedDateString() ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $row['notes'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No distribution batches recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">10. Collection Monitoring Summary</h2>
        <p class="mt-2 text-sm text-slate-600">Overall readiness: <strong>{{ $packageData['collection']['readiness']['status'] }}</strong></p>
        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Current</th>
                        <th class="px-4 py-3">Minimum</th>
                        <th class="px-4 py-3">Target</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Due Date</th>
                        <th class="px-4 py-3">Follow-up</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($packageData['collection']['sources'] as $source)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $source['label'] }}</td>
                            <td class="px-4 py-3">{{ $source['current_count'] }}</td>
                            <td class="px-4 py-3">{{ $source['minimum_count'] }}</td>
                            <td class="px-4 py-3">{{ $source['target_count'] }}</td>
                            <td class="px-4 py-3">{{ $source['status_label'] }}</td>
                            <td class="px-4 py-3">{{ $source['target']->due_date?->toFormattedDateString() ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $source['suggested_action'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">11. Synthesis Matrix</h2>
        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Theme</th>
                        <th class="px-4 py-3">Finding</th>
                        <th class="px-4 py-3">Evidence</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Design Implication</th>
                        <th class="px-4 py-3">Development Decision</th>
                        <th class="px-4 py-3">Module</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($packageData['synthesis_items'] as $item)
                        <tr>
                            <td class="px-4 py-3">{{ AnalysisSynthesisItem::SOURCE_LABELS[$item->source_type] ?? $label($item->source_type) }}</td>
                            <td class="px-4 py-3">{{ AnalysisSynthesisItem::THEME_LABELS[$item->theme] ?? $label($item->theme) }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $item->finding }}</td>
                            <td class="px-4 py-3">{{ $item->evidence_summary ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $label($item->priority_level) }}</td>
                            <td class="px-4 py-3">{{ $item->design_implication ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->development_decision ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->mapped_module ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $label($item->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">No synthesis items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">12. Readiness Recommendation</h2>
        <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-sm leading-7 text-emerald-950">{{ $packageData['readiness_recommendation'] }}</p>
    </section>

    <section class="{{ $sectionClass }}">
        <h2 class="text-2xl font-semibold">13. Supervisor Review Notes</h2>
        <div class="mt-5 grid gap-4">
            @foreach (['Catatan Ketua Promotor', 'Catatan Co-Promotor', 'Catatan Revisi'] as $labelText)
                <div class="min-h-32 rounded-lg border border-slate-300 p-4">
                    <p class="text-sm font-semibold">{{ $labelText }}</p>
                </div>
            @endforeach
            <div class="rounded-lg border border-slate-300 p-4">
                <p class="text-sm font-semibold">Keputusan</p>
                <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <p>[ ] Dapat dilanjutkan ke tahap Design</p>
                    <p>[ ] Perlu revisi minor</p>
                    <p>[ ] Perlu revisi mayor</p>
                    <p>[ ] Perlu pengumpulan data tambahan</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-8">
        <h2 class="text-2xl font-semibold">14. Signature / Approval Placeholder</h2>
        <div class="mt-8 grid gap-8 sm:grid-cols-2">
            @foreach (['Peneliti', 'Ketua Promotor', 'Co-Promotor 1', 'Co-Promotor 2'] as $role)
                <div class="pt-16 text-center">
                    <div class="border-t border-slate-400 pt-3">
                        <p class="font-semibold">{{ $role }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</article>
