@props(['title', 'description' => null, 'action' => null])

<header class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-3xl text-sm text-zinc-600">{{ $description }}</p>
        @endif
    </div>
    @if ($action)
        <div>{{ $action }}</div>
    @endif
</header>
