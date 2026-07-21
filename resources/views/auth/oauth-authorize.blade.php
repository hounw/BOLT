<x-layouts.app>
    <section class="mx-auto flex min-h-[calc(100vh-65px)] w-full max-w-lg flex-col justify-center px-6 py-12">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-zinc-500">Connected application</p>
            <h1 class="mt-2 text-2xl font-semibold">Authorize {{ $client->name }}</h1>
            <p class="mt-2 text-sm leading-6 text-zinc-600">
                This application is requesting access to your BOLT account. Only continue if you recognize it.
            </p>

            @if (count($scopes) > 0)
                <div class="mt-6">
                    <h2 class="text-sm font-semibold">Requested access</h2>
                    <ul class="mt-3 divide-y divide-zinc-100 rounded-md border border-zinc-200">
                        @foreach ($scopes as $scope)
                            <li class="px-4 py-3">
                                <p class="text-sm font-medium">{{ $scope->description }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $scope->id }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Deny</button>
                </form>
                <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Authorize</button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
