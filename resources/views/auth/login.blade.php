<x-layouts.app>
    <section class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6">
        <div class="mb-8">
            <a href="/" class="text-sm font-semibold text-zinc-600">BOLT</a>
            <h1 class="mt-6 text-3xl font-semibold">Sign in</h1>
            <p class="mt-2 text-sm text-zinc-600">Use your BOLT account to manage operations and authorize connected apps.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
            @csrf

            <label class="block">
                <span class="text-sm font-medium">Email</span>
                <input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                @error('email')
                    <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium">Password</span>
                <input name="password" type="password" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                @error('password')
                    <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex items-center gap-2 text-sm text-zinc-700">
                <input name="remember" type="checkbox" class="rounded border-zinc-300">
                Remember this device
            </label>

            <button type="submit" class="w-full rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Sign in</button>
        </form>
    </section>
</x-layouts.app>
