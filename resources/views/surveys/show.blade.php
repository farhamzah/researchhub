<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $survey->title }} - MyRiset Survey</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-8 border-b border-gray-200 pb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Survey</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $survey->title }}</h1>
            @if ($survey->description)
                <p class="mt-3 text-base leading-7 text-gray-600">{{ $survey->description }}</p>
            @endif
        </header>

        <form method="POST" action="{{ route('survey.responses.store', ['survey' => $survey->slug]) }}" class="space-y-6">
            @csrf

            @if (in_array($survey->identity_mode, [\App\Models\Survey::IDENTITY_FULL, \App\Models\Survey::IDENTITY_HIDDEN], true))
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold">Respondent Identity</h2>
                    <p class="mt-1 text-sm text-gray-600">Identity data is stored separately from answers.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="identity_name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input id="identity_name" name="identity[name]" value="{{ old('identity.name') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="identity_email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input id="identity_email" name="identity[email]" type="email" value="{{ old('identity.email') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="identity_identifier" class="block text-sm font-medium text-gray-700">Identifier</label>
                            <input id="identity_identifier" name="identity[identifier]" value="{{ old('identity.identifier') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="identity_institution" class="block text-sm font-medium text-gray-700">Institution</label>
                            <input id="identity_institution" name="identity[institution]" value="{{ old('identity.institution') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                    </div>
                </section>
            @elseif ($survey->identity_mode === \App\Models\Survey::IDENTITY_PSEUDONYM)
                <section class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-sm">
                    Your response will be stored under a generated respondent code.
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
                Submit Response
            </button>
        </form>
    </main>
</body>
</html>
