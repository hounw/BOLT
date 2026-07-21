<x-layouts.app>
    <section class="mx-auto w-full max-w-3xl px-6 py-8">
        <x-page-header :title="$endpoint->exists ? 'Edit webhook' : 'New webhook'" />
        <x-panel class="mt-6">
            <form method="POST" action="{{ $endpoint->exists ? route('webhooks.update', $endpoint) : route('webhooks.store') }}" class="grid gap-5">
                @csrf
                @if ($endpoint->exists)
                    @method('PUT')
                @endif
                <label class="block text-sm font-medium">Name<input name="name" value="{{ old('name', $endpoint->name) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                <label class="block text-sm font-medium">URL<input name="url" value="{{ old('url', $endpoint->url) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                <label class="block text-sm font-medium">
                    Signing secret
                    <input name="secret" type="password" placeholder="{{ $endpoint->exists ? 'Leave blank to keep existing secret' : '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    <span class="mt-1 block text-xs font-normal text-zinc-500">{{ $endpoint->exists ? 'Leave blank to keep the existing encrypted secret. Enter a new value to rotate it. Secrets are never displayed again.' : 'Use a long random value. Secrets are encrypted and never displayed again.' }}</span>
                </label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $endpoint->is_active ?? true))> Active</label>
                <fieldset>
                    <legend class="text-sm font-medium">Events</legend>
                    <div class="mt-2 grid gap-2 md:grid-cols-2">
                        @foreach ($events as $event => $description)
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', $endpoint->events ?? []), true)) class="mt-1">
                                <span>
                                    <span class="block font-medium">{{ $event }}</span>
                                    <span class="block text-zinc-500">{{ $description }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @if ($errors->any())<div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                <div class="flex justify-end gap-3"><a href="{{ route('webhooks.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save</button></div>
            </form>
        </x-panel>
    </section>
</x-layouts.app>
