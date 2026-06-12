@php
    $statusBadge = fn (string $status): string => match ($status) {
        \App\Models\ExpertValidatorProject::STATUS_ACTIVE => 'bg-emerald-50 text-emerald-800',
        \App\Models\ExpertValidatorProject::STATUS_COMPLETED => 'bg-blue-50 text-blue-800',
        \App\Models\ExpertValidatorProject::STATUS_DECLINED => 'bg-gray-100 text-gray-600',
        default => 'bg-amber-50 text-amber-800',
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Validators - MyRiset</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">MyRiset Projects</p>
                <h1 class="mt-2 text-3xl font-semibold">Project Validators</h1>
                <p class="mt-2 text-sm text-gray-600">{{ $project->title }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('filament.admin.resources.expert-validators.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Expert Registry
                </a>
                <a href="{{ url('/admin') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    Back to Admin
                </a>
            </div>
        </div>

        @if (session('status'))
            <section class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
                Validator assignment updated.
            </section>
        @endif

        @if ($errors->any())
            <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Please review the validator assignment form.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Assign Expert Validator</h2>
                    <p class="mt-1 text-sm text-gray-600">Only active validators visible to your account can be assigned to this project.</p>
                </div>
            </div>

            @if ($availableValidators === [])
                <div class="mt-5 rounded-md border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-600">
                    No active expert validators are available. Create a private validator in the registry or ask a super admin to publish a global validator.
                </div>
            @else
                <form method="POST" action="{{ route('admin.projects.validators.store', ['researchProject' => $project]) }}" class="mt-5 grid gap-4 lg:grid-cols-3">
                    @csrf
                    <div>
                        <label for="expert_validator_id" class="block text-sm font-medium text-gray-700">Expert Validator</label>
                        <select id="expert_validator_id" name="expert_validator_id" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            @foreach ($availableValidators as $validatorId => $label)
                                <option value="{{ $validatorId }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select id="role" name="role" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            @foreach ($roleLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="expertise_scope" class="block text-sm font-medium text-gray-700">Expertise Scope</label>
                        <input id="expertise_scope" name="expertise_scope" value="{{ old('expertise_scope') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="invited_at" class="block text-sm font-medium text-gray-700">Invited At</label>
                        <input id="invited_at" name="invited_at" type="date" value="{{ old('invited_at') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="accepted_at" class="block text-sm font-medium text-gray-700">Accepted At</label>
                        <input id="accepted_at" name="accepted_at" type="date" value="{{ old('accepted_at') }}" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div class="lg:col-span-3">
                        <label for="notes" class="block text-sm font-medium text-gray-700">Internal Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="lg:col-span-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">
                        Assign Validator
                    </button>
                </form>
            @endif
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Assigned Validators</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-3 pr-4">Validator</th>
                            <th class="py-3 pr-4">Role</th>
                            <th class="py-3 pr-4">Scope</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Timeline</th>
                            <th class="py-3 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td class="py-3 pr-4">
                                    <p class="font-medium">{{ $assignment->validator?->name ?? 'Missing validator' }}</p>
                                    <p class="text-xs text-gray-500">{{ $assignment->validator?->institution ?? 'No institution recorded' }}</p>
                                </td>
                                <td class="py-3 pr-4">{{ $roleLabels[$assignment->role] ?? $assignment->role }}</td>
                                <td class="py-3 pr-4">{{ $assignment->expertise_scope ?? 'Not set' }}</td>
                                <td class="py-3 pr-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge($assignment->status) }}">{{ $statusLabels[$assignment->status] ?? $assignment->status }}</span>
                                </td>
                                <td class="py-3 pr-4">
                                    <p>Invited: {{ $assignment->invited_at?->format('Y-m-d') ?? 'Not set' }}</p>
                                    <p>Accepted: {{ $assignment->accepted_at?->format('Y-m-d') ?? 'Not set' }}</p>
                                </td>
                                <td class="py-3 pr-4">
                                    <details>
                                        <summary class="cursor-pointer text-sm font-semibold text-emerald-700">Edit</summary>
                                        <form method="POST" action="{{ route('admin.projects.validators.update', ['researchProject' => $project, 'assignment' => $assignment]) }}" class="mt-3 grid min-w-[520px] gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2">
                                            @csrf
                                            @method('PUT')
                                            <select name="role" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                @foreach ($roleLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected($assignment->role === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                                @foreach ($statusLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected($assignment->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input name="expertise_scope" value="{{ $assignment->expertise_scope }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                            <input name="invited_at" type="date" value="{{ $assignment->invited_at?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                            <input name="accepted_at" type="date" value="{{ $assignment->accepted_at?->format('Y-m-d') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                                            <textarea name="notes" rows="2" class="sm:col-span-2 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">{{ $assignment->notes }}</textarea>
                                            <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600">Save Assignment</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.projects.validators.destroy', ['researchProject' => $project, 'assignment' => $assignment]) }}" class="mt-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Detach this validator from the project?')" class="rounded-md border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50">
                                                Detach Validator
                                            </button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-gray-500">No expert validators assigned yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
