<x-filament-panels::page>
    <div class="space-y-6">
        @if (! $credentialsConfigured)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Google OAuth credentials are not configured. Add safe values to the local environment before testing the live OAuth redirect.
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Connection status</p>
                    <h2 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                        {{ $isConnected ? 'Connected' : 'Not connected' }}
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-300">
                        Live Google OAuth requires configured Google Cloud credentials and a redirect URI that matches this application.
                    </p>
                </div>

                <div class="flex shrink-0 gap-3">
                    @if ($isConnected)
                        <form method="POST" action="{{ $disconnectUrl }}">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            >
                                Disconnect Google Drive
                            </button>
                        </form>
                    @else
                        <a
                            href="{{ $connectUrl }}"
                            class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                        >
                            Connect Google Drive
                        </a>
                    @endif
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-md border border-gray-100 p-4 dark:border-gray-800">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="mt-2 text-sm font-medium text-gray-950 dark:text-white">
                        {{ $connection?->email ?: 'Not available' }}
                    </dd>
                </div>

                <div class="rounded-md border border-gray-100 p-4 dark:border-gray-800">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Last connected</dt>
                    <dd class="mt-2 text-sm font-medium text-gray-950 dark:text-white">
                        {{ $connection?->last_connected_at?->format('Y-m-d H:i') ?: 'Not available' }}
                    </dd>
                </div>

                <div class="rounded-md border border-gray-100 p-4 dark:border-gray-800">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Token expiry</dt>
                    <dd class="mt-2 text-sm font-medium text-gray-950 dark:text-white">
                        {{ $connection?->token_expires_at?->format('Y-m-d H:i') ?: 'Not available' }}
                    </dd>
                </div>

                <div class="rounded-md border border-gray-100 p-4 dark:border-gray-800">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-2 text-sm font-medium text-gray-950 dark:text-white">
                        {{ $connection?->status ?: 'Not connected' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Required scope</h3>
            <div class="mt-3 space-y-2">
                @foreach ($requiredScopes as $scope)
                    <code class="block rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $scope }}</code>
                @endforeach
            </div>
        </div>

        @if ($connection?->last_error)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                {{ $connection->last_error }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
