<x-layouts.app>
    <section class="mx-auto w-full max-w-2xl px-6 py-8">
        <x-page-header title="Account" description="Update your login password." />

        <x-panel class="mt-6">
            <form method="POST" action="{{ route('account.password.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <label class="block text-sm font-medium">
                    Current password
                    <input name="current_password" type="password" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    @error('current_password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="block text-sm font-medium">
                    New password
                    <input name="password" type="password" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    @error('password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="block text-sm font-medium">
                    Confirm new password
                    <input name="password_confirmation" type="password" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Update password</button>
                </div>
            </form>
        </x-panel>
    </section>
</x-layouts.app>
