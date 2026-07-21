<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header title="Knowledge categories" description="Browse operational knowledge by category." />

        <div class="mt-6 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
            @forelse ($categories as $item)
                <a href="{{ route('knowledge-categories.show', $item['category']) }}" class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4 last:border-0 hover:bg-zinc-50" style="padding-left: {{ 1.25 + ($item['depth'] * 1.5) }}rem">
                    <span class="min-w-0"><span class="block truncate font-medium">{{ $item['category']->name }}</span><span class="mt-0.5 block truncate text-xs text-zinc-500">{{ $item['path'] }}</span></span>
                    <span class="shrink-0 text-sm text-zinc-500">{{ $item['article_count'] }} {{ Str::plural('article', $item['article_count']) }}</span>
                </a>
            @empty
                <div class="px-6 py-16 text-center"><p class="font-medium">No categories yet</p><p class="mt-1 text-sm text-zinc-500">Knowledge managers can add categories in Knowledge setup.</p></div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
