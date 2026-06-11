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

        @if (session('generated_review_url'))
            <section class="mb-6 rounded-lg border border-green-200 bg-green-50 p-5 text-green-900">
                <h2 class="text-base font-semibold">Review link created</h2>
                <p class="mt-1 text-sm">Copy this URL now. ResearchHub will not show it again after this page changes.</p>
                <input readonly value="{{ session('generated_review_url') }}" class="mt-3 block w-full rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm">
            </section>
        @elseif (session('status') === 'review-link-revoked')
            <section class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                Review link revoked.
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,420px)]">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Existing Links</h2>
                <p class="mt-1 text-sm text-gray-600">Existing links show metadata only. Raw review URLs are not stored.</p>

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
                                    <td class="py-4 pr-4">{{ $reviewLink->status }}</td>
                                    <td class="py-4 pr-4">{{ $reviewLink->expires_at?->format('Y-m-d H:i') ?: 'No expiry' }}</td>
                                    <td class="py-4 pr-4">{{ $reviewLink->access_count }}{{ $reviewLink->max_access_count ? ' / '.$reviewLink->max_access_count : '' }}</td>
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
                                        @if ($reviewLink->status !== \App\Models\ReviewLink::STATUS_REVOKED)
                                            <form method="POST" action="{{ route('admin.documents.review-links.revoke', ['document' => $document, 'reviewLink' => $reviewLink]) }}">
                                                @csrf
                                                <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-500">
                                                    Revoke
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-500">Revoked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-sm text-gray-500">No review links yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold">Create Link</h2>
                <p class="mt-1 text-sm text-gray-600">Default expiry is 7 days. The raw URL appears only once.</p>

                <form method="POST" action="{{ route('admin.documents.review-links.store', ['document' => $document]) }}" class="mt-6 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="label">Label</label>
                        <input id="label" name="label" value="{{ old('label') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        @error('label')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="reviewer_name">Reviewer name</label>
                            <input id="reviewer_name" name="reviewer_name" value="{{ old('reviewer_name') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="reviewer_email">Reviewer email</label>
                            <input id="reviewer_email" name="reviewer_email" type="email" value="{{ old('reviewer_email') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="expires_at">Expires at</label>
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
                        <p class="mt-1 text-xs text-gray-500">Used only when the permission preset is custom.</p>
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
                        <label class="block text-sm font-medium text-gray-700" for="password">Password optional</label>
                        <input id="password" name="password" type="password" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="max_access_count">Max access count optional</label>
                        <input id="max_access_count" name="max_access_count" type="number" min="1" value="{{ old('max_access_count') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="document_version_id">Document version optional</label>
                        <select id="document_version_id" name="document_version_id" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            <option value="">Current version</option>
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
