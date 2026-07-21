<x-layouts.app>
    <section class="mx-auto w-full max-w-5xl px-6 py-8">
        <x-page-header title="Version history" :description="$article->title">
            <x-slot:action><a href="{{ route('knowledge.show', $article) }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Back to article</a></x-slot:action>
        </x-page-header>

        <div class="mt-6 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
            @foreach ($versions as $version)
                <div class="flex flex-col gap-4 border-b border-zinc-200 px-5 py-5 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2"><span class="font-semibold">Version {{ $version->version }}</span>@if ($version->version === $article->version)<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Current</span>@endif</div>
                        <p class="mt-1 text-sm text-zinc-500">{{ $version->created_at->format('M j, Y g:i A') }} · {{ $version->editor?->name ?? 'System' }} · {{ str($version->status?->value)->title() }}</p>
                        <p class="mt-2 text-sm text-zinc-700">{{ $version->title }}@if ($version->category) · {{ $version->category }}@endif</p>
                        @if ($version->excerpt)<p class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ $version->excerpt }}</p>@endif
                    </div>
                    @if ($version->version !== $article->version)
                        <a href="{{ route('knowledge.versions.restore', [$article, $version]) }}" class="shrink-0 rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Load in editor</a>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $versions->links() }}</div>
    </section>
</x-layouts.app>
