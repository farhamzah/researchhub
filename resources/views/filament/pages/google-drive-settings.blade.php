<x-filament-panels::page>
    @php
        $connectionBadge = $isConnected
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-slate-200 bg-slate-50 text-slate-700';

        $readinessBadge = $credentialsConfigured
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-amber-200 bg-amber-50 text-amber-800';

        $healthBadge = match ($healthStatus) {
            'Healthy' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'Token expired', 'Connection failed' => 'border-red-200 bg-red-50 text-red-700',
            'Credentials missing' => 'border-amber-200 bg-amber-50 text-amber-800',
            default => 'border-blue-200 bg-blue-50 text-blue-700',
        };
    @endphp

    <div class="space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Google Drive Integration</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">Connection Status</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Connect MyRiset to your own Google Drive account so future document workflows can store file metadata safely without exposing tokens or secrets.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-semibold {{ $connectionBadge }}">
                        {{ $isConnected ? 'Connected' : 'Not connected' }}
                    </span>
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-semibold {{ $healthBadge }}">
                        {{ $healthStatus }}
                    </span>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Google account</dt>
                    <dd class="mt-2 break-words text-sm font-semibold text-slate-950">
                        {{ $connection?->email ?: 'Not connected' }}
                    </dd>
                </div>

                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last connected</dt>
                    <dd class="mt-2 text-sm font-semibold text-slate-950">
                        {{ $connection?->last_connected_at?->format('Y-m-d H:i') ?: 'Not available' }}
                    </dd>
                </div>

                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Token expiry</dt>
                    <dd class="mt-2 text-sm font-semibold {{ $tokenExpired ? 'text-red-700' : 'text-slate-950' }}">
                        {{ $connection?->token_expires_at?->format('Y-m-d H:i') ?: 'Not available' }}
                    </dd>
                </div>

                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stored status</dt>
                    <dd class="mt-2 text-sm font-semibold text-slate-950">
                        {{ ucfirst($connection?->status ?: 'disconnected') }}
                    </dd>
                </div>

                <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Privacy boundary</dt>
                    <dd class="mt-2 text-sm font-semibold text-slate-950">
                        Current user only
                    </dd>
                </div>
            </dl>

            @if ($connection?->last_error)
                <div class="mt-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">
                    Last connection error: {{ $connection->last_error }}
                </div>
            @endif
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">OAuth Setup</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950">Configuration Readiness</h3>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full border px-3 py-1 text-sm font-semibold {{ $readinessBadge }}">
                        {{ $credentialsConfigured ? 'Ready' : 'Not configured' }}
                    </span>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client ID configured</dt>
                        <dd class="mt-2 text-sm font-semibold {{ $clientIdConfigured ? 'text-emerald-700' : 'text-amber-800' }}">
                            {{ $clientIdConfigured ? 'Yes' : 'No' }}
                        </dd>
                        <dd class="mt-1 break-words text-xs text-slate-500">
                            {{ $maskedClientId }}
                        </dd>
                    </div>

                    <div class="rounded-md border border-slate-100 bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client secret configured</dt>
                        <dd class="mt-2 text-sm font-semibold {{ $clientSecretConfigured ? 'text-emerald-700' : 'text-amber-800' }}">
                            {{ $clientSecretConfigured ? 'Yes' : 'No' }}
                        </dd>
                        <dd class="mt-1 text-xs text-slate-500">
                            Value hidden for security.
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 space-y-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Redirect URI to add in Google Cloud Console</p>
                        <code class="mt-2 block overflow-x-auto rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ $configuredRedirectUri }}</code>
                        @if ($configuredRedirectUri !== $routeRedirectUri)
                            <p class="mt-2 text-xs leading-5 text-amber-700">
                                Current route URL resolves to {{ $routeRedirectUri }}. Make sure APP_URL and GOOGLE_REDIRECT_URI match the local URL you use in the browser.
                            </p>
                        @else
                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                Make sure APP_URL matches the local URL you use in the browser.
                            </p>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-700">Required OAuth scope</p>
                        <div class="mt-2 space-y-2">
                            @forelse ($requiredScopes as $scope)
                                <code class="block overflow-x-auto rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ $scope }}</code>
                            @empty
                                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                                    No Google Drive scope is configured.
                                </p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Actions</p>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">Manage Connection</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Connect is available only after OAuth credentials are configured. Refresh is always safe and only reloads this status page.
                </p>

                <div class="mt-6 flex flex-col gap-3">
                    @if ($isConnected)
                        <form method="POST" action="{{ $disconnectUrl }}">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Disconnect Google Drive for this user? Local OAuth tokens will be cleared from MyRiset.')"
                                class="inline-flex w-full items-center justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            >
                                Disconnect / Revoke Local Connection
                            </button>
                        </form>
                    @else
                        @if ($credentialsConfigured)
                            <a
                                href="{{ $connectUrl }}"
                                class="inline-flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                            >
                                Connect Google Drive
                            </a>
                        @else
                            <button
                                type="button"
                                disabled
                                class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-500"
                            >
                                Connect Google Drive unavailable
                            </button>
                        @endif
                    @endif

                    <a
                        href="{{ $refreshUrl }}"
                        class="inline-flex w-full items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        Refresh Status
                    </a>
                </div>

                @unless ($credentialsConfigured)
                    <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        OAuth credentials are missing. Add safe local values to `.env`, run `php artisan optimize:clear`, then return here.
                    </div>
                @endunless
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Local Development Setup</p>
            <h3 class="mt-2 text-xl font-semibold text-slate-950">Safe Setup Instructions</h3>

            <ol class="mt-5 grid gap-3 text-sm leading-6 text-slate-700 md:grid-cols-2">
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">1.</span> Create or open a Google Cloud project.</li>
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">2.</span> Enable the Google Drive API.</li>
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">3.</span> Configure the OAuth consent screen.</li>
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">4.</span> Create an OAuth client with type Web application.</li>
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">5.</span> Add the redirect URI shown on this page.</li>
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">6.</span> Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to local `.env`.</li>
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">7.</span> Run `php artisan optimize:clear` after changing environment values.</li>
                <li class="rounded-md border border-slate-100 bg-slate-50 p-4"><span class="font-semibold text-slate-950">8.</span> Return here and click Connect Google Drive.</li>
            </ol>

            <div class="mt-5 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                MyRiset requests only the Drive file scope for files created or opened by the app. Do not paste OAuth secrets into tickets, chats, screenshots, or source files.
            </div>
        </section>
    </div>
</x-filament-panels::page>
