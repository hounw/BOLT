<x-layouts.app>
    <section class="mx-auto grid min-h-screen w-full max-w-6xl items-center gap-10 px-6 py-12 lg:grid-cols-[1.1fr_.9fr]">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Business Operations Low-code Toolkit</p>
            <h1 class="mt-4 max-w-3xl text-5xl font-semibold leading-tight">BOLT</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-zinc-600">
                A public Laravel base app for the internal operations layer small businesses keep rebuilding: HR records, PTO, files, knowledge, assets, audit, API, and webhooks.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Sign in</a>
                @endauth
                <a href="/docs" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">API docs</a>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Core modules</h2>
            <div class="mt-5 grid gap-3 text-sm text-zinc-700">
                <p>Employees, roles, compensation history, benefits, PTO approval, and manager relationships.</p>
                <p>Private attachments, Markdown knowledge base, asset tracking, audit log, and webhook delivery.</p>
                <p>OAuth2 via Passport and documented `/api/v1` endpoints for agents and external apps.</p>
            </div>
        </div>
    </section>
</x-layouts.app>
