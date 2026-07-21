<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="API tokens" description="Create scoped bearer tokens for agents, MCP clients, and integrations." />

        @if ($plainToken)
            <x-panel class="mt-6 border-amber-200 bg-amber-50">
                <p class="text-sm font-semibold text-amber-900">Copy this token now. It will not be shown again.</p>
                <code class="mt-3 block break-all rounded-md border border-amber-200 bg-white px-3 py-2 text-sm text-zinc-900">{{ $plainToken }}</code>
            </x-panel>
        @endif

        <x-panel class="mt-6">
            <form method="POST" action="{{ route('access.tokens.store') }}" class="space-y-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium">
                        Token name
                        <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        @error('name')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block text-sm font-medium">
                        Acting user
                        <select name="user_id" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                            <option value="">Select a user</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }} · {{ $user->email }}</option>
                            @endforeach
                        </select>
                        @error('user_id')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>

                <fieldset>
                    <legend class="text-sm font-semibold">Scopes</legend>
                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        @foreach ($scopes as $scope)
                            <label class="flex items-start gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm">
                                <input type="checkbox" name="scopes[]" value="{{ $scope->id }}" @checked(in_array($scope->id, old('scopes', []), true))>
                                <span>
                                    <span class="block font-medium">{{ $scope->id }}</span>
                                    <span class="block text-zinc-500">{{ $scope->description }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('scopes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('scopes.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Create token</button>
                </div>
            </form>
        </x-panel>

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('access.tokens.index') }}" class="grid gap-4 lg:grid-cols-4">
                <label class="block text-sm font-medium">
                    Search
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>
                <label class="block text-sm font-medium">
                    Acting user
                    <select name="user_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }} · {{ $user->email }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Status
                    <select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="revoked" @selected(($filters['status'] ?? '') === 'revoked')>Revoked</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">
                    Scope
                    <select name="scope" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                        <option value="">Any scope</option>
                        @foreach ($scopes as $scope)
                            <option value="{{ $scope->id }}" @selected(($filters['scope'] ?? '') === $scope->id)>{{ $scope->id }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end justify-end gap-3 lg:col-span-4">
                    <a href="{{ route('access.tokens.index') }}" class="text-sm font-medium text-zinc-600">Clear</a>
                    <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>
        </x-panel>

        <x-panel class="mt-6 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Token</th>
                        <th class="px-4 py-3 font-medium">Acting user</th>
                        <th class="px-4 py-3 font-medium">Scopes</th>
                        <th class="px-4 py-3 font-medium">Expires</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($tokens as $token)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $token->name ?: 'Unnamed token' }}</div>
                                <div class="text-zinc-500">{{ $token->revoked ? 'Revoked' : 'Active' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600">{{ $tokenUsers->get($token->user_id)?->email ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ collect($token->scopes ?? [])->join(', ') ?: '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $token->expires_at?->toDateString() ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if (! $token->revoked)
                                    <form method="POST" action="{{ route('access.tokens.revoke', $token) }}">
                                        @csrf
                                        @method('PUT')
                                        <button class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Revoke</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No API tokens yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-panel>

        <div class="mt-4">{{ $tokens->links() }}</div>
    </section>
</x-layouts.app>
