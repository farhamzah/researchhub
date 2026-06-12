<x-filament-panels::page>
    @php
        $connectionLabel = $isConnected ? 'Connected' : 'Not connected';
        $storedStatus = ucfirst($connection?->status ?: 'disconnected');
        $readinessLabel = $credentialsConfigured ? 'Ready' : 'Not configured';
        $visibleRedirectUri = $configuredRedirectUri ?: $routeRedirectUri;

        $statusClass = $isConnected ? 'drive-badge drive-badge-success' : 'drive-badge drive-badge-muted';
        $readinessClass = $credentialsConfigured ? 'drive-badge drive-badge-success' : 'drive-badge drive-badge-warning';
        $healthClass = match ($healthStatus) {
            'Healthy' => 'drive-badge drive-badge-success',
            'Token expired', 'Connection failed' => 'drive-badge drive-badge-danger',
            'Credentials missing' => 'drive-badge drive-badge-warning',
            default => 'drive-badge drive-badge-info',
        };
        $folderStatusClass = match ($folderBootstrapStatus) {
            'Ready' => 'drive-badge drive-badge-success',
            'Partially created' => 'drive-badge drive-badge-warning',
            default => 'drive-badge drive-badge-muted',
        };

        $folderStatusMessage = session('status');
        $folderStatusParts = is_string($folderStatusMessage) && str_starts_with($folderStatusMessage, 'myriset-drive-folders-ready:')
            ? explode(':', $folderStatusMessage)
            : null;
    @endphp

    <style>
        .myriset-drive-page {
            color: #0f172a;
        }

        .myriset-drive-page * {
            box-sizing: border-box;
        }

        .drive-hero,
        .drive-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .drive-hero {
            padding: 24px;
        }

        .drive-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 20px;
        }

        .drive-card {
            padding: 22px;
        }

        .drive-card-wide {
            grid-column: 1 / -1;
        }

        .drive-eyebrow {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            margin: 0;
            text-transform: uppercase;
        }

        .drive-title {
            color: #0f172a;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
            margin: 8px 0 0;
        }

        .drive-card-title {
            color: #0f172a;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.3;
            margin: 8px 0 0;
        }

        .drive-copy {
            color: #475569;
            font-size: 14px;
            line-height: 1.65;
            margin: 10px 0 0;
        }

        .drive-header-row,
        .drive-action-row {
            align-items: flex-start;
            display: flex;
            gap: 12px;
            justify-content: space-between;
        }

        .drive-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .drive-badge {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            min-height: 28px;
            padding: 7px 10px;
            white-space: nowrap;
        }

        .drive-badge-success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #047857;
        }

        .drive-badge-warning {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .drive-badge-danger {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #be123c;
        }

        .drive-badge-info {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .drive-badge-muted {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #475569;
        }

        .drive-facts {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin: 18px 0 0;
        }

        .drive-fact {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            min-width: 0;
            padding: 14px;
        }

        .drive-fact-label {
            color: #64748b;
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .drive-fact-value {
            color: #0f172a;
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-top: 7px;
            overflow-wrap: anywhere;
        }

        .drive-code {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #0f172a;
            display: block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            line-height: 1.55;
            margin-top: 10px;
            overflow-x: auto;
            padding: 12px;
            white-space: pre;
        }

        .drive-alert {
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 16px;
            padding: 14px;
        }

        .drive-alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .drive-alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .drive-alert-danger {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #be123c;
        }

        .drive-checklist {
            counter-reset: drive-step;
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            list-style: none;
            margin: 18px 0 0;
            padding: 0;
        }

        .drive-checklist li {
            align-items: flex-start;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            color: #334155;
            display: flex;
            font-size: 14px;
            gap: 10px;
            line-height: 1.55;
            padding: 12px;
        }

        .drive-checklist li::before {
            align-items: center;
            background: #2563eb;
            border-radius: 999px;
            color: #ffffff;
            content: counter(drive-step);
            counter-increment: drive-step;
            display: inline-flex;
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 700;
            height: 24px;
            justify-content: center;
            margin-top: 1px;
            width: 24px;
        }

        .drive-actions {
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .drive-button {
            align-items: center;
            border-radius: 10px;
            display: inline-flex;
            font-size: 14px;
            font-weight: 700;
            justify-content: center;
            min-height: 42px;
            padding: 10px 14px;
            text-decoration: none;
            transition: background 120ms ease, border-color 120ms ease;
            width: 100%;
        }

        .drive-button-primary {
            background: #2563eb;
            border: 1px solid #2563eb;
            color: #ffffff;
        }

        .drive-button-primary:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .drive-button-secondary {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
        }

        .drive-button-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .drive-button-danger {
            background: #e11d48;
            border: 1px solid #e11d48;
            color: #ffffff;
        }

        .drive-button-danger:hover {
            background: #be123c;
            color: #ffffff;
        }

        .drive-button-disabled {
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            color: #64748b;
            cursor: not-allowed;
        }

        .drive-roadmap {
            display: grid;
            gap: 10px;
            list-style: none;
            margin: 18px 0 0;
            padding: 0;
        }

        .drive-roadmap li {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            color: #334155;
            font-size: 14px;
            line-height: 1.55;
            padding: 12px;
        }

        @media (max-width: 900px) {
            .drive-grid,
            .drive-facts,
            .drive-checklist {
                grid-template-columns: 1fr;
            }

            .drive-header-row,
            .drive-action-row {
                display: block;
            }

            .drive-badge-row {
                justify-content: flex-start;
                margin-top: 14px;
            }
        }
    </style>

    <div class="myriset-drive-page">
        @if ($errors->has('google_drive'))
            <div class="drive-alert drive-alert-danger" role="alert">
                {{ $errors->first('google_drive') }}
            </div>
        @endif

        <section class="drive-hero" aria-labelledby="google-drive-settings-title">
            <div class="drive-header-row">
                <div>
                    <p class="drive-eyebrow">Google Drive Integration</p>
                    <h2 id="google-drive-settings-title" class="drive-title">Google Drive Settings</h2>
                    <p class="drive-copy">Connect MyRiset to your own Google Drive account.</p>
                    <p class="drive-copy">
                        Use Google Drive to prepare future document workflows while keeping tokens and secrets private.
                    </p>
                </div>

                <div class="drive-badge-row" aria-label="Google Drive status summary">
                    <span class="{{ $statusClass }}">{{ $connectionLabel }}</span>
                    <span class="{{ $readinessClass }}">{{ $readinessLabel }}</span>
                    <span class="{{ $healthClass }}">{{ $healthStatus }}</span>
                </div>
            </div>
        </section>

        <div class="drive-grid">
            <section class="drive-card" data-testid="drive-status-card" aria-labelledby="drive-status-card-title">
                <div class="drive-header-row">
                    <div>
                        <p class="drive-eyebrow">Connection Status</p>
                        <h3 id="drive-status-card-title" class="drive-card-title">Current Google Drive connection</h3>
                    </div>
                    <span class="{{ $statusClass }}">{{ $connectionLabel }}</span>
                </div>

                <dl class="drive-facts">
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Google account</dt>
                        <dd class="drive-fact-value">{{ $connection?->email ?: 'Not connected' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Last connected</dt>
                        <dd class="drive-fact-value">{{ $connection?->last_connected_at?->format('Y-m-d H:i') ?: 'Not available' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Token expiry</dt>
                        <dd class="drive-fact-value">{{ $connection?->token_expires_at?->format('Y-m-d H:i') ?: 'Not available' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Stored status</dt>
                        <dd class="drive-fact-value">{{ $storedStatus }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Privacy boundary</dt>
                        <dd class="drive-fact-value">Current user only</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Status detail</dt>
                        <dd class="drive-fact-value">{{ $healthStatus }}</dd>
                    </div>
                </dl>

                @if ($connection?->last_error)
                    <div class="drive-alert drive-alert-warning">
                        A previous connection error was recorded. The exact secret-bearing OAuth payload is not displayed here.
                    </div>
                @endif
            </section>

            <section class="drive-card" data-testid="oauth-readiness-card" aria-labelledby="oauth-readiness-card-title">
                <div class="drive-header-row">
                    <div>
                        <p class="drive-eyebrow">OAuth Readiness</p>
                        <h3 id="oauth-readiness-card-title" class="drive-card-title">Configuration readiness</h3>
                    </div>
                    <span class="{{ $readinessClass }}">{{ $readinessLabel }}</span>
                </div>

                <dl class="drive-facts">
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Client ID configured</dt>
                        <dd class="drive-fact-value">{{ $clientIdConfigured ? 'Yes' : 'No' }}</dd>
                        <dd class="drive-copy">{{ $maskedClientId }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Client secret configured</dt>
                        <dd class="drive-fact-value">{{ $clientSecretConfigured ? 'Yes' : 'No' }}</dd>
                        <dd class="drive-copy">Value hidden for security.</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Redirect URI configured</dt>
                        <dd class="drive-fact-value">{{ $redirectUriConfigured ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Configuration readiness</dt>
                        <dd class="drive-fact-value">{{ $readinessLabel }}</dd>
                    </div>
                </dl>

                @unless ($credentialsConfigured)
                    <div class="drive-alert drive-alert-warning">
                        OAuth credentials are not configured yet. Add them to your local .env, clear cache, then refresh this page.
                    </div>
                @endunless
            </section>

            <section class="drive-card" data-testid="redirect-scope-card" aria-labelledby="redirect-scope-card-title">
                <p class="drive-eyebrow">Redirect URI and Scope</p>
                <h3 id="redirect-scope-card-title" class="drive-card-title">Copy these values into Google Cloud</h3>
                <p class="drive-copy">
                    The redirect URI must match the web application OAuth client in Google Cloud Console.
                </p>

                <div class="drive-facts">
                    <div class="drive-fact">
                        <span class="drive-fact-label">Authorized redirect URI</span>
                        <code class="drive-code">{{ $visibleRedirectUri }}</code>
                    </div>
                    <div class="drive-fact">
                        <span class="drive-fact-label">Required scope</span>
                        <code class="drive-code">{{ $primaryScope }}</code>
                    </div>
                </div>

                @if (count($requiredScopes) > 1)
                    <div class="drive-alert drive-alert-info">
                        Additional configured scopes:
                        @foreach (array_slice($requiredScopes, 1) as $scope)
                            <code>{{ $scope }}</code>@if (! $loop->last), @endif
                        @endforeach
                    </div>
                @endif

                @if (! $redirectUriConfigured)
                    <div class="drive-alert drive-alert-warning">
                        GOOGLE_REDIRECT_URI is not configured. The route currently resolves to {{ $routeRedirectUri }}.
                    </div>
                @elseif ($redirectUriMismatch)
                    <div class="drive-alert drive-alert-warning">
                        Configured redirect URI differs from the route URL. Configured: {{ $configuredRedirectUri }}. Route URL: {{ $routeRedirectUri }}.
                    </div>
                @endif

                <div class="drive-alert drive-alert-info">
                    Production redirect URI for myriset.net: <code>{{ $productionRedirectUri }}</code>
                </div>
            </section>

            <section class="drive-card" data-testid="drive-actions-card" aria-labelledby="drive-actions-card-title">
                <p class="drive-eyebrow">Actions</p>
                <h3 id="drive-actions-card-title" class="drive-card-title">Manage connection</h3>
                <p class="drive-copy">
                    Connect is available only when OAuth readiness is complete. Refresh is safe and only reloads this page.
                </p>

                <div class="drive-actions">
                    @if ($isConnected)
                        <form method="POST" action="{{ $disconnectUrl }}">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Disconnect Google Drive for this user? Local OAuth tokens will be cleared from MyRiset.')"
                                class="drive-button drive-button-danger"
                            >
                                Disconnect / Revoke Connection
                            </button>
                        </form>
                    @elseif ($credentialsConfigured)
                        <a href="{{ $connectUrl }}" class="drive-button drive-button-primary">
                            Connect Google Drive
                        </a>
                    @else
                        <button type="button" disabled class="drive-button drive-button-disabled" aria-disabled="true">
                            Connect Google Drive unavailable
                        </button>
                    @endif

                    <a href="{{ $refreshUrl }}" class="drive-button drive-button-secondary">
                        Refresh Status
                    </a>
                </div>

                <div class="drive-alert drive-alert-info">
                    JSON status endpoint for diagnostics: <code>{{ $statusUrl }}</code>. It never returns access tokens or refresh tokens.
                </div>
            </section>

            <section class="drive-card" data-testid="drive-folder-bootstrap-card" aria-labelledby="drive-folder-bootstrap-card-title">
                <div class="drive-header-row">
                    <div>
                        <p class="drive-eyebrow">Drive Folder Bootstrap</p>
                        <h3 id="drive-folder-bootstrap-card-title" class="drive-card-title">Create MyRiset folder structure</h3>
                    </div>
                    <span class="{{ $folderStatusClass }}">{{ $folderBootstrapStatus }}</span>
                </div>

                <p class="drive-copy">
                    Prepare standard folders in the connected user's Google Drive. The action reuses stored folder IDs and searches by name before creating new folders.
                </p>

                <dl class="drive-facts">
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Root folder name</dt>
                        <dd class="drive-fact-value">{{ $rootFolderName }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Root folder ID</dt>
                        <dd class="drive-fact-value">{{ $rootFolderIdPreview }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Global folders</dt>
                        <dd class="drive-fact-value">{{ $globalFolderCount }} / {{ $expectedGlobalFolderCount }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Project folders</dt>
                        <dd class="drive-fact-value">{{ $projectFolderCount }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Last bootstrap</dt>
                        <dd class="drive-fact-value">{{ $lastBootstrapAt ?: 'Not available' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Privacy boundary</dt>
                        <dd class="drive-fact-value">Current user Drive only</dd>
                    </div>
                </dl>

                @if ($folderStatusParts)
                    <div class="drive-alert drive-alert-info">
                        MyRiset Drive folders are ready. Created {{ $folderStatusParts[1] ?? 0 }} folder(s), reused {{ $folderStatusParts[2] ?? 0 }} folder(s).
                    </div>
                @endif

                <div class="drive-actions">
                    @if ($isConnected)
                        <form method="POST" action="{{ $bootstrapFoldersUrl }}">
                            @csrf
                            <button type="submit" class="drive-button drive-button-primary">
                                Create MyRiset Folders
                            </button>
                        </form>
                    @else
                        <button type="button" disabled class="drive-button drive-button-disabled" aria-disabled="true">
                            Connect Google Drive first
                        </button>
                    @endif

                    <a href="{{ $refreshUrl }}" class="drive-button drive-button-secondary">
                        Refresh Folder Status
                    </a>
                </div>
            </section>

            <section class="drive-card drive-card-wide" data-testid="setup-checklist-card" aria-labelledby="setup-checklist-card-title">
                <p class="drive-eyebrow">Setup Checklist</p>
                <h3 id="setup-checklist-card-title" class="drive-card-title">Prepare Google Cloud OAuth</h3>

                <ol class="drive-checklist">
                    <li>Open Google Cloud Console.</li>
                    <li>Create or select a Google Cloud project.</li>
                    <li>Enable Google Drive API.</li>
                    <li>Configure OAuth consent screen.</li>
                    <li>Create OAuth Client ID with type Web application.</li>
                    <li>Add the redirect URI shown on this page.</li>
                    <li>Copy Client ID and Client Secret.</li>
                    <li>Add them to your local .env file.</li>
                    <li>Run php artisan optimize:clear.</li>
                    <li>Refresh this page and click Connect Google Drive.</li>
                </ol>

                <p class="drive-copy">Safe local placeholder snippet. Do not paste real secrets into source control.</p>
                <code class="drive-code">GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI={{ $visibleRedirectUri }}
GOOGLE_DRIVE_SCOPES="https://www.googleapis.com/auth/drive.file"</code>
            </section>

            <section class="drive-card drive-card-wide" data-testid="drive-roadmap-card" aria-labelledby="drive-roadmap-card-title">
                <p class="drive-eyebrow">Coming Next</p>
                <h3 id="drive-roadmap-card-title" class="drive-card-title">Planned Google workspace integrations</h3>
                <ul class="drive-roadmap">
                    <li>Export validation and supervision reports to Google Docs.</li>
                    <li>Export survey and validation data to Google Sheets.</li>
                    <li>Bootstrap project folders in Google Drive.</li>
                </ul>
            </section>
        </div>
    </div>
</x-filament-panels::page>
