<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'BOLT') }}</title>
    <link rel="icon" href="/bolt-icon.png">
    <link rel="apple-touch-icon" href="/bolt-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="min-h-screen bg-zinc-50 text-zinc-950">
        @auth
            <header class="border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-3 md:flex-nowrap md:gap-6">
                    <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center" aria-label="BOLT dashboard">
                        <img src="/bolt-logo.png" alt="BOLT" class="h-8 w-auto">
                    </a>
                    <nav class="order-3 flex w-full flex-wrap items-center justify-center gap-1 text-sm text-zinc-600 md:order-none md:w-auto md:flex-1" aria-label="Primary navigation">
                        @foreach (config('navigation.main') as $item)
                            <x-nav-link :item="$item" />
                        @endforeach
                    </nav>
                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <a href="{{ route('account.password') }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium">Account</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium">Sign out</button>
                        </form>
                    </div>
                </div>
            </header>
        @endauth

        @if (session('status'))
            <div class="mx-auto mt-4 max-w-7xl px-6">
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
