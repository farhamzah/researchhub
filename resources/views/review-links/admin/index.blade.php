<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Links - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">ResearchHub Admin</p>
                <h1 class="mt-2 text-3xl font-semibold">Review Links</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $document->title }} - {{ $document->project->title }}</p>
            </div>
            <a href="{{ route('filament.admin.resources.documents.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                Back to Documents
            </a>
        </div>

        {{-- UX-S10-09: Token warning shown as high-visibility alert banner --}}
        @if (session('generated_review_url'))
            <section class="mb-6 rounded-lg border-2 border-amber-400 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div class="flex-1">
                        <h2 class="text-base font-bold text-amber-900">Copy this review URL now - it will not be shown again</h2>
                        <p class="mt-1 text-sm text-amber-800">ResearchHub stores only a hash of the token. Once you navigate away from this page, the raw URL cannot be recovered. Copy and send it to the reviewer immediately.</p>
                        <div class="mt-3 flex items-center gap-2">
                            <input id="review-url-once" readonly value="{{ session('generated_review_url') }}" class="block w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-mono text-gray-900 shadow-sm">
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('review-url-once').value).then(function(){ this.textContent='Copied!'; }.bind(this))" class="flex-shrink-0 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
                                Copy
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        @elseif (session('status') === 'review-link-revoked')
            <section class="mb-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm">
                Review link has been revoked. The reviewer can no longer access the document using that link.
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,420px)]">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Existing Links</h2>
                <p class="mt-1 text-sm text-gray-600">Metadata only. Raw review URLs are not stored and cannot be recovered after creation.</p>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="py-3 pr-4">Label</th>
                                <th class="py-3 pr-4">Reviewer</th>
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3 pr-4">Expires</th>
                                <th class="py-3 pr-4">Access</th>
                                <th class="py-3 pr-4">Permissions</th>
                                <th class="py-3 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($reviewLinks as $reviewLink)
                                <tr>
                                    <td class="py-4 pr-4 font-medium">{{ $reviewLink->label ?: 'Review link' }}</td>
                                    <td class="py-4 pr-4">
                                        <div>{{ $reviewLink->reviewer_name ?: 'Guest reviewer' }}</div>
                                        <div class="text-xs text-gray-500">{{ $reviewLink->reviewer_email }}</div>
                                    </td>
                                    {{-- UX-S10-01 / LI-03: Status badge with color coding --}}
                                    <td class="py-4 pr-4">
                                        @if ($reviewLink->status === \App\Models\ReviewLink::STATUS_ACTIVE)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                        @elseif ($reviewLink->status === \App\Models\ReviewLink::STATUS_EXPIRED)
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">Expired</span>
                                        @elseif ($reviewLink->status === \App\Models\ReviewLink::STATUS_REVOKED)
                                            <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">Revoked</span>
                                        @elseif ($reviewLink->status === \App\Models\ReviewLink::STATUS_DISABLED)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Disabled</span>
                                        @else
                                            <span class="text-xs text-gray-500">{{ $reviewLink->status }}</span>
                                        @endif
                                    </td>
                                    <td class="py-4 pr-4 text-sm">{{ $reviewLink->expires_at?->format('Y-m-d H:i') ?: 'No expiry' }}</td>
                                    <td class="py-4 pr-4 text-sm">{{ $reviewLink->access_count }}{{ $reviewLink->max_access_count ? ' / '.$reviewLink->max_access_count : '' }}</td>
                                    <td class="py-4 pr-4">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($reviewLink->permissions ?? [] as $permission => $enabled)
                                                @if ($enabled)
                                                    <span class="rounded bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">{{ str_replace('_', ' ', $permission) }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-4 pr-4">
                                        {{-- UX-S10-01: Revoke confirmation guard --}}
                                        @if ($reviewLink->status !== \App\Models\ReviewLink::STATUS_REVOKED)
                                            <form method="POST" action="{{ route('admin.documents.review-links.revoke', ['document' => $document, 'reviewLink' => $reviewLink]) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50"
                                                    onclick="return confirm('Revoke this review link?\n\nThis will immediately disable reviewer access. This action cannot be undone.')"
                                                >
                                                    Revoke
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">Revoked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-10 text-center">
                                        <p class="text-sm font-medium text-gray-500">No review links yet.</p>
                                        <p class="mt-1 text-xs text-gray-400">Create your first review link using the form on the right.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Create Review Link</h2>
                {{-- UX-S10-09: Clearer once-only notice before form --}}
                <div class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>Security notice:</strong> The raw review URL is shown <em>once only</em> after creation. Store it safely or send it directly to the reviewer. ResearchHub cannot recover it.
                </div>

                <form method="POST" action="{{ route('admin.documents.review-links.store', ['document' => $document]) }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="label">Label</label>
                        <input id="label" name="label" value="{{ old('label') }}" placeholder="e.g. Supervisor Review - Chapter I" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        @error('label')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="reviewer_name">Reviewer name</label>
                            <input id="reviewer_name" name="reviewer_name" value="{{ old('reviewer_name') }}" placeholder="e.g. Dr. Ahmad" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="reviewer_email">Reviewer email</label>
                            <input id="reviewer_email" name="reviewer_email" type="email" value="{{ old('reviewer_email') }}" placeholder="reviewer@institution.edu" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="expires_at">Expires at <span class="font-normal text-gray-500">(default: 7 days)</span></label>
                        <input id="expires_at" name="expires_at" type="datetime-local" required value="{{ old('expires_at', now()->addDays(7)->format('Y-m-d\TH:i')) }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        @error('expires_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="permission_preset">Permission preset</label>
                        <select id="permission_preset" name="permission_preset" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            @foreach ($permissionPresets as $key => $permissions)
                                <option value="{{ $key }}" @selected(old('permission_preset', 'view_only') === $key)>{{ str_replace('_', ' ', ucfirst($key)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Custom permissions</legend>
                        <p class="mt-1 text-xs text-gray-500">Active only when "Custom" preset is selected. View permission is always granted.</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($defaultPermissions as $permission => $enabled)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="hidden" name="permissions[{{ $permission }}]" value="0">
                                    <input type="checkbox" name="permissions[{{ $permission }}]" value="1" @checked(old("permissions.{$permission}", $enabled)) @disabled($permission === 'view') class="rounded border-gray-300 text-blue-700">
                                    {{ str_replace('_', ' ', $permission) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="password">Password <span class="font-normal text-gray-500">(optional)</span></label>
                        <input id="password" name="password" type="password" placeholder="Leave blank for no password" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="max_access_count">Max access count <span class="font-normal text-gray-500">(optional)</span></label>
                        <input id="max_access_count" name="max_access_count" type="number" min="1" value="{{ old('max_access_count') }}" placeholder="Leave blank for unlimited" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">Limit how many times this link can be opened. Leave blank for unlimited access.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="document_version_id">Document version <span class="font-normal text-gray-500">(optional)</span></label>
                        <select id="document_version_id" name="document_version_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <option value="">Current version (default)</option>
                            @foreach ($document->versions->sortByDesc('version_number') as $version)
                                <option value="{{ $version->id }}" @selected(old('document_version_id') === $version->id)>v{{ $version->version_number }} - {{ $version->original_file_name ?: $version->file_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600">
                        Create Review Link
                    </button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
