<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $survey->title }} - Survey MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        @php
            $introImageUrl = $survey->intro_image_url;
            $hasIntro = filled($survey->intro_text) || filled($introImageUrl);
            $showQuestionsImmediately = ! $hasIntro || $errors->any() || old('intro_consent') === '1';
            $introConsentText = $survey->consent_text ?: 'Saya telah membaca penjelasan di atas dan bersedia melanjutkan.';
        @endphp

        @if (($pilotRun ?? null) instanceof \App\Models\AnalysisPilotRun)
            <section class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <p class="font-semibold">MODE UJI COBA / REVIEWER</p>
                <p class="mt-1">Respons dari tautan ini tidak masuk hasil analisis. Gunakan hanya untuk pratinjau pembimbing/reviewer atau uji coba, bukan untuk distribusi responden utama.</p>
            </section>
        @endif

        <header class="mb-8 border-b border-gray-200 pb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Survey MyRiset</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $survey->title }}</h1>
            @if ($survey->description)
                <p class="mt-3 text-base leading-7 text-gray-600">{{ $survey->description }}</p>
            @endif
        </header>

        @if ($hasIntro)
            <section id="survey-intro" data-intro-gate class="{{ $showQuestionsImmediately ? 'hidden' : '' }} rounded-lg border border-emerald-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Pengantar Survey</p>
                <h2 class="mt-2 text-2xl font-semibold">{{ $survey->intro_title ?: $survey->title }}</h2>

                @if ($survey->estimated_duration)
                    <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Perkiraan waktu</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ $survey->estimated_duration }}</p>
                    </div>
                @endif

                @if ($introImageUrl)
                    <figure class="mt-5 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                        <img src="{{ $introImageUrl }}" alt="{{ $survey->intro_image_alt_text ?: 'Survey intro illustration' }}" loading="lazy" decoding="async" class="aspect-video w-full object-cover">
                        @if ($survey->intro_image_caption || $survey->intro_image_source_note)
                            <figcaption class="border-t border-gray-200 px-4 py-3 text-xs leading-5 text-gray-600">
                                @if ($survey->intro_image_caption)
                                    <span>{{ $survey->intro_image_caption }}</span>
                                @endif
                                @if ($survey->intro_image_source_note)
                                    <span class="block text-gray-500">{{ $survey->intro_image_source_note }}</span>
                                @endif
                            </figcaption>
                        @endif
                    </figure>
                @endif

                @if ($survey->intro_text)
                    <p class="mt-5 whitespace-pre-line text-sm leading-7 text-gray-700">{{ $survey->intro_text }}</p>
                @endif

                <dl class="mt-5 grid gap-3">
                    @if ($survey->privacy_statement)
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Privasi</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-700">{{ $survey->privacy_statement }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($survey->respondent_instruction)
                    <div class="mt-5 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950">
                        <p class="font-semibold">Petunjuk Pengisian</p>
                        <p class="mt-1">{{ $survey->respondent_instruction }}</p>
                    </div>
                @endif

                @if ($survey->require_consent_before_start)
                    <label for="intro_consent_gate" class="mt-5 flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                        <input id="intro_consent_gate" type="checkbox" class="mt-1 rounded border-amber-300 text-emerald-700">
                        <span>{{ $introConsentText }}</span>
                    </label>
                    @error('intro_consent')
                        <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                @endif

                <button type="button" id="survey_intro_continue" @disabled($survey->require_consent_before_start) class="mt-6 w-full rounded-md bg-emerald-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600 disabled:cursor-not-allowed disabled:bg-gray-300">
                    Lanjut ke Pertanyaan
                </button>
            </section>
        @endif

        <form id="survey-response-form" method="POST" action="{{ route('survey.responses.store', ['survey' => $survey->slug]) }}" class="{{ $showQuestionsImmediately ? '' : 'hidden' }} space-y-6">
            @csrf
            <input type="hidden" id="intro_consent" name="intro_consent" value="{{ old('intro_consent', $survey->require_consent_before_start ? '0' : '1') }}">
            @if (filled($pilotToken ?? null))
                <input type="hidden" name="pilot" value="{{ $pilotToken }}">
            @endif

            @if (in_array($survey->identity_mode, [\App\Models\Survey::IDENTITY_FULL, \App\Models\Survey::IDENTITY_HIDDEN], true))
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold">Identitas Responden</h2>
                    <p class="mt-1 text-sm text-gray-600">Data identitas disimpan terpisah dari jawaban survey.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="identity_name" class="block text-sm font-medium text-gray-700">Nama</label>
                            <input id="identity_name" name="identity[name]" value="{{ old('identity.name') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="identity_email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input id="identity_email" name="identity[email]" type="email" value="{{ old('identity.email') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="identity_identifier" class="block text-sm font-medium text-gray-700">Kode / NIM / ID</label>
                            <input id="identity_identifier" name="identity[identifier]" value="{{ old('identity.identifier') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="identity_institution" class="block text-sm font-medium text-gray-700">Institusi</label>
                            <input id="identity_institution" name="identity[institution]" value="{{ old('identity.institution') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                    </div>
                </section>
            @elseif ($survey->identity_mode === \App\Models\Survey::IDENTITY_PSEUDONYM)
                <section class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-sm">
                    Respons Anda akan disimpan dengan kode responden otomatis.
                </section>
            @endif

            @foreach ($survey->pages as $page)
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    @if ($page->title)
                        <h2 class="text-xl font-semibold">{{ $page->title }}</h2>
                    @endif
                    @if ($page->description)
                        <p class="mt-1 text-sm text-gray-600">{{ $page->description }}</p>
                    @endif

                    <div class="mt-5 space-y-5">
                        @foreach ($page->questions as $question)
                            @include('surveys.partials.question', ['question' => $question])
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- Questions inside pages render above. Only questions with no page assignment render here. --}}
            @php $unpagedQuestions = $survey->questions->whereNull('page_id'); @endphp
            @if ($survey->pages->isEmpty() && $survey->questions->isNotEmpty())
                {{-- Survey has no pages at all: render all questions in a single section --}}
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="space-y-5">
                        @foreach ($survey->questions as $question)
                            @include('surveys.partials.question', ['question' => $question])
                        @endforeach
                    </div>
                </section>
            @elseif ($survey->pages->isNotEmpty() && $unpagedQuestions->isNotEmpty())
                {{-- Survey has pages AND some questions with no page assignment --}}
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="space-y-5">
                        @foreach ($unpagedQuestions as $question)
                            @include('surveys.partials.question', ['question' => $question])
                        @endforeach
                    </div>
                </section>
            @endif

            <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                Kirim Respons
            </button>
        </form>
    </main>

    @if ($hasIntro)
        <script>
            (() => {
                const intro = document.querySelector('[data-intro-gate]');
                const form = document.getElementById('survey-response-form');
                const continueButton = document.getElementById('survey_intro_continue');
                const gateConsent = document.getElementById('intro_consent_gate');
                const formConsent = document.getElementById('intro_consent');

                if (! intro || ! form || ! continueButton) {
                    return;
                }

                gateConsent?.addEventListener('change', () => {
                    continueButton.disabled = ! gateConsent.checked;
                    formConsent.value = gateConsent.checked ? '1' : '0';
                });

                continueButton.addEventListener('click', () => {
                    if (gateConsent && ! gateConsent.checked) {
                        return;
                    }

                    intro.classList.add('hidden');
                    form.classList.remove('hidden');
                    formConsent.value = '1';
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            })();
        </script>
    @endif
</body>
</html>
