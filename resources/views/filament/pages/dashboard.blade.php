<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Welcome to ResearchHub</h1>
            <p class="mt-2 text-base text-gray-600">
                Manage your research projects, documents, surveys, analysis, and academic drafts in one place.
            </p>
        </section>

        <!-- Stats -->
        <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Research Projects</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $projectCount }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Documents</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $documentCount }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Surveys</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $surveyCount }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Analysis Results</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $analysisResultCount }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Pending Reviews</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $activeReviewCount }}</p>
            </div>
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Next Steps -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Recommended next steps</h2>
                <ul class="mt-4 space-y-3 text-sm text-gray-600 list-decimal list-inside">
                    <li>Create or open a research project.</li>
                    <li>Connect Google Drive.</li>
                    <li>Upload proposal/chapter documents.</li>
                    <li>Create survey instruments.</li>
                    <li>Run descriptive analysis.</li>
                </ul>
            </section>

            <!-- Quick Actions -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('filament.admin.resources.documents.index') }}" class="rounded-lg border border-gray-200 p-4 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-emerald-700 transition-colors">
                        <span class="block">Open Documents &rarr;</span>
                    </a>
                    <a href="{{ route('filament.admin.resources.surveys.index') }}" class="rounded-lg border border-gray-200 p-4 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-emerald-700 transition-colors">
                        <span class="block">Open Surveys &rarr;</span>
                    </a>
                    <a href="{{ route('filament.admin.pages.settings.google-drive') }}" class="rounded-lg border border-gray-200 p-4 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-emerald-700 transition-colors">
                        <span class="block">Google Drive Settings &rarr;</span>
                    </a>
                    @if ($driveConnected)
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                            <span class="block">✓ Drive Connected</span>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
