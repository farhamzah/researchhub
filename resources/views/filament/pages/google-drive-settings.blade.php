<x-filament-panels::page>
    @php
        $connectionLabel = $isConnected ? 'Terhubung' : 'Belum terhubung';
        $storedStatus = match ($connection?->status ?: 'disconnected') {
            'connected' => 'Terhubung',
            'failed' => 'Gagal',
            default => 'Belum terhubung',
        };
        $readinessLabel = $credentialsConfigured ? 'Siap' : 'Belum dikonfigurasi';
        $healthLabel = match ($healthStatus) {
            'Healthy' => 'Sehat',
            'Token expired' => 'Token kedaluwarsa',
            'Connection failed' => 'Koneksi gagal',
            'Credentials missing' => 'Credential belum lengkap',
            default => 'Siap dihubungkan',
        };
        $folderStatusLabel = match ($folderBootstrapStatus) {
            'Ready' => 'Siap',
            'Partially created' => 'Sebagian dibuat',
            default => 'Belum dibuat',
        };
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
            border: 1px solid #dbe4ee;
            border-radius: 8px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
        }

        .drive-hero {
            background: linear-gradient(135deg, #ffffff 0%, #ffffff 58%, #f8fbff 100%);
            padding: 26px;
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
            font-size: 30px;
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
            border-radius: 8px;
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
            border-radius: 8px;
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
            border-radius: 8px;
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
            border-radius: 8px;
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
            border-radius: 8px;
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
            border-radius: 8px;
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
                    <p class="drive-eyebrow">Integrasi Google Drive</p>
                    <h2 id="google-drive-settings-title" class="drive-title">Hubungkan MyRiset ke Google Drive</h2>
                    <p class="drive-copy">Gunakan akun Google Drive Anda sendiri untuk menyimpan file, folder, dan hasil ekspor riset.</p>
                    <p class="drive-copy">
                        MyRiset tetap menjadi pusat workflow dan metadata. Google Drive hanya dipakai untuk file, folder, dan ekspor.
                    </p>
                </div>

                <div class="drive-badge-row" aria-label="Google Drive status summary">
                    <span class="{{ $statusClass }}">{{ $connectionLabel }}</span>
                    <span class="{{ $readinessClass }}">{{ $readinessLabel }}</span>
                    <span class="{{ $healthClass }}">{{ $healthLabel }}</span>
                </div>
            </div>
        </section>

        <div class="drive-grid">
            <section class="drive-card" data-testid="drive-status-card" aria-labelledby="drive-status-card-title">
                <div class="drive-header-row">
                    <div>
                        <p class="drive-eyebrow">Status Koneksi</p>
                        <h3 id="drive-status-card-title" class="drive-card-title">Koneksi Google Drive saat ini</h3>
                    </div>
                    <span class="{{ $statusClass }}">{{ $connectionLabel }}</span>
                </div>

                <dl class="drive-facts">
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Akun Google</dt>
                        <dd class="drive-fact-value">{{ $connection?->email ?: 'Belum terhubung' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Terakhir terhubung</dt>
                        <dd class="drive-fact-value">{{ $connection?->last_connected_at?->format('Y-m-d H:i') ?: 'Belum tersedia' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Token kedaluwarsa</dt>
                        <dd class="drive-fact-value">{{ $connection?->token_expires_at?->format('Y-m-d H:i') ?: 'Belum tersedia' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Status tersimpan</dt>
                        <dd class="drive-fact-value">{{ $storedStatus }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Batas privasi</dt>
                        <dd class="drive-fact-value">Hanya pengguna saat ini</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Detail status</dt>
                        <dd class="drive-fact-value">{{ $healthLabel }}</dd>
                    </div>
                </dl>

                @if ($connection?->last_error)
                    <div class="drive-alert drive-alert-warning">
                        Error koneksi sebelumnya tercatat. Payload OAuth yang mungkin berisi rahasia tidak ditampilkan di halaman ini.
                    </div>
                @endif
            </section>

            <section class="drive-card" data-testid="oauth-readiness-card" aria-labelledby="oauth-readiness-card-title">
                <div class="drive-header-row">
                    <div>
                        <p class="drive-eyebrow">Kesiapan OAuth</p>
                        <h3 id="oauth-readiness-card-title" class="drive-card-title">Kesiapan konfigurasi</h3>
                    </div>
                    <span class="{{ $readinessClass }}">{{ $readinessLabel }}</span>
                </div>

                <dl class="drive-facts">
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Client ID terisi</dt>
                        <dd class="drive-fact-value">{{ $clientIdConfigured ? 'Ya' : 'Belum' }}</dd>
                        <dd class="drive-copy">{{ $maskedClientId }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Client Secret terisi</dt>
                        <dd class="drive-fact-value">{{ $clientSecretConfigured ? 'Ya' : 'Belum' }}</dd>
                        <dd class="drive-copy">Nilai disembunyikan demi keamanan.</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Redirect URI terisi</dt>
                        <dd class="drive-fact-value">{{ $redirectUriConfigured ? 'Ya' : 'Belum' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Kesiapan konfigurasi</dt>
                        <dd class="drive-fact-value">{{ $readinessLabel }}</dd>
                    </div>
                </dl>

                @unless ($credentialsConfigured)
                    <div class="drive-alert drive-alert-warning">
                        Google Drive belum dikonfigurasi. Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET di .env server, clear cache, lalu refresh halaman ini.
                    </div>
                @endunless
            </section>

            <section class="drive-card" data-testid="redirect-scope-card" aria-labelledby="redirect-scope-card-title">
                <p class="drive-eyebrow">Redirect URI dan Scope</p>
                <h3 id="redirect-scope-card-title" class="drive-card-title">Salin nilai ini ke Google Cloud</h3>
                <p class="drive-copy">
                    Redirect URI utama harus sama persis dengan OAuth client aplikasi web di Google Cloud Console.
                </p>

                <div class="drive-facts">
                    <div class="drive-fact">
                        <span class="drive-fact-label">Redirect URI utama</span>
                        <code class="drive-code">{{ $visibleRedirectUri }}</code>
                    </div>
                    <div class="drive-fact">
                        <span class="drive-fact-label">Scope wajib</span>
                        <code class="drive-code">{{ $primaryScope }}</code>
                    </div>
                    <div class="drive-fact">
                        <span class="drive-fact-label">Contoh redirect lokal</span>
                        <code class="drive-code">{{ $localExampleRedirectUri }}</code>
                    </div>
                    <div class="drive-fact">
                        <span class="drive-fact-label">Contoh redirect produksi</span>
                        <code class="drive-code">{{ $productionRedirectUri }}</code>
                    </div>
                </div>

                @if (count($requiredScopes) > 1)
                    <div class="drive-alert drive-alert-info">
                        Scope tambahan yang dikonfigurasi:
                        @foreach (array_slice($requiredScopes, 1) as $scope)
                            <code>{{ $scope }}</code>@if (! $loop->last), @endif
                        @endforeach
                    </div>
                @endif

                @if (! $redirectUriConfigured)
                    <div class="drive-alert drive-alert-warning">
                        GOOGLE_REDIRECT_URI belum dikonfigurasi. Route saat ini mengarah ke {{ $routeRedirectUri }}.
                    </div>
                @elseif ($redirectUriMismatch)
                    <div class="drive-alert drive-alert-warning">
                        Redirect URI konfigurasi berbeda dari URL route. Konfigurasi: {{ $configuredRedirectUri }}. URL route: {{ $routeRedirectUri }}.
                    </div>
                @endif

                <div class="drive-alert drive-alert-info">
                    Alias kompatibilitas opsional jika diaktifkan di Google Cloud: <code>{{ $optionalAliasRedirectUri }}</code>. Tetap jadikan route utama di atas sebagai URI primer.
                </div>

                <div class="drive-alert drive-alert-warning">
                    Jika Google menampilkan <code>redirect_uri_mismatch</code>, salin Redirect URI utama dari halaman ini ke Google Cloud Console persis sama. Cocokkan protokol, domain atau 127.0.0.1, port, dan path.
                </div>
            </section>

            <section class="drive-card" data-testid="drive-actions-card" aria-labelledby="drive-actions-card-title">
                <p class="drive-eyebrow">Aksi Utama</p>
                <h3 id="drive-actions-card-title" class="drive-card-title">Kelola koneksi</h3>
                <p class="drive-copy">
                    Tombol hubungkan aktif setelah konfigurasi OAuth lengkap. Muat ulang aman karena hanya memperbarui status halaman ini.
                </p>

                <div class="drive-actions">
                    @if ($isConnected)
                        <form method="POST" action="{{ $disconnectUrl }}">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Putuskan Google Drive untuk pengguna ini? Token OAuth lokal akan dihapus dari MyRiset.')"
                                class="drive-button drive-button-danger"
                            >
                                Putuskan Koneksi
                            </button>
                        </form>
                    @elseif ($credentialsConfigured)
                        <a href="{{ $connectUrl }}" class="drive-button drive-button-primary">
                            Hubungkan Google Drive
                        </a>
                    @else
                        <button type="button" disabled class="drive-button drive-button-disabled" aria-disabled="true">
                            Lengkapi OAuth dulu
                        </button>
                    @endif

                    <a href="{{ $refreshUrl }}" class="drive-button drive-button-secondary">
                        Muat Ulang Status
                    </a>
                </div>

                <div class="drive-alert drive-alert-info">
                    Endpoint status JSON untuk diagnostik: <code>{{ $statusUrl }}</code>. Endpoint ini tidak mengembalikan access token atau refresh token.
                </div>
            </section>

            <section class="drive-card" data-testid="drive-folder-bootstrap-card" aria-labelledby="drive-folder-bootstrap-card-title">
                <div class="drive-header-row">
                    <div>
                        <p class="drive-eyebrow">Folder Google Drive</p>
                        <h3 id="drive-folder-bootstrap-card-title" class="drive-card-title">Siapkan struktur folder MyRiset</h3>
                    </div>
                    <span class="{{ $folderStatusClass }}">{{ $folderStatusLabel }}</span>
                </div>

                <p class="drive-copy">
                    MyRiset menyiapkan folder standar di Google Drive pengguna yang terhubung. Jika folder sudah ada, sistem akan memakai ulang sebelum membuat folder baru.
                </p>

                <dl class="drive-facts">
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Nama folder utama</dt>
                        <dd class="drive-fact-value">{{ $rootFolderName }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">ID folder utama</dt>
                        <dd class="drive-fact-value">{{ $rootFolderIdPreview }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Folder global</dt>
                        <dd class="drive-fact-value">{{ $globalFolderCount }} / {{ $expectedGlobalFolderCount }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Folder proyek</dt>
                        <dd class="drive-fact-value">{{ $projectFolderCount }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Terakhir disiapkan</dt>
                        <dd class="drive-fact-value">{{ $lastBootstrapAt ?: 'Belum tersedia' }}</dd>
                    </div>
                    <div class="drive-fact">
                        <dt class="drive-fact-label">Batas privasi</dt>
                        <dd class="drive-fact-value">Hanya Drive pengguna saat ini</dd>
                    </div>
                </dl>

                @if ($folderStatusParts)
                    <div class="drive-alert drive-alert-info">
                        Folder MyRiset Drive siap. Membuat {{ $folderStatusParts[1] ?? 0 }} folder dan memakai ulang {{ $folderStatusParts[2] ?? 0 }} folder.
                    </div>
                @elseif ($isConnected && $globalFolderCount === 0)
                    <div class="drive-alert drive-alert-warning">
                        Google Drive sudah terhubung, tetapi folder MyRiset belum dibuat. Klik Siapkan Folder MyRiset.
                    </div>
                @endif

                <div class="drive-actions">
                    @if ($isConnected)
                        <form method="POST" action="{{ $bootstrapFoldersUrl }}">
                            @csrf
                            <button type="submit" class="drive-button drive-button-primary">
                                Siapkan Folder MyRiset
                            </button>
                        </form>
                    @else
                        <button type="button" disabled class="drive-button drive-button-disabled" aria-disabled="true">
                            Hubungkan Google Drive dulu
                        </button>
                    @endif

                    <a href="{{ $refreshUrl }}" class="drive-button drive-button-secondary">
                        Muat Ulang Status Folder
                    </a>
                </div>
            </section>

            <section class="drive-card drive-card-wide" data-testid="setup-checklist-card" aria-labelledby="setup-checklist-card-title">
                <p class="drive-eyebrow">Checklist Setup</p>
                <h3 id="setup-checklist-card-title" class="drive-card-title">Siapkan OAuth Google Cloud</h3>

                <ol class="drive-checklist">
                    <li>Buka Google Cloud Console.</li>
                    <li>Buat atau pilih project Google Cloud.</li>
                    <li>Aktifkan Google Drive API.</li>
                    <li>Atur OAuth consent screen.</li>
                    <li>Buat OAuth Client ID dengan tipe Web application.</li>
                    <li>Tambahkan Redirect URI yang tampil di halaman ini.</li>
                    <li>Salin Client ID dan Client Secret.</li>
                    <li>Tambahkan ke file .env lokal atau konfigurasi server.</li>
                    <li>Jalankan php artisan optimize:clear.</li>
                    <li>Muat ulang halaman ini lalu klik Hubungkan Google Drive.</li>
                </ol>

                <p class="drive-copy">Contoh placeholder yang aman. Jangan masukkan secret asli ke source control.</p>
                <code class="drive-code">GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI={{ $visibleRedirectUri }}
GOOGLE_DRIVE_SCOPES="https://www.googleapis.com/auth/drive.file"</code>
            </section>

            <section class="drive-card drive-card-wide" data-testid="drive-roadmap-card" aria-labelledby="drive-roadmap-card-title">
                <p class="drive-eyebrow">Berikutnya</p>
                <h3 id="drive-roadmap-card-title" class="drive-card-title">Integrasi Google Workspace yang direncanakan</h3>
                <ul class="drive-roadmap">
                    <li>Ekspor laporan validasi dan bimbingan ke Google Docs.</li>
                    <li>Ekspor data survey dan validasi ke Google Sheets.</li>
                    <li>Siapkan folder proyek langsung di Google Drive.</li>
                </ul>
            </section>
        </div>
    </div>
</x-filament-panels::page>
