<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header :title="$category->name" :description="$category->path()">
            <x-slot:action><a href="{{ route('knowledge-categories.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">All categories</a></x-slot:action>
        </x-page-header>

        @if ($children->isNotEmpty())
            <section class="mt-6">
                <h2 class="text-sm font-semibold uppercase text-zinc-500">Subcategories</h2>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach ($children as $child)
                        <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3"><a href="{{ route('knowledge-categories.show', $child) }}" class="font-semibold hover:underline">{{ $child->name }}</a><span class="text-xs text-zinc-500">{{ $child->articles_count }} {{ Str::plural('article', $child->articles_count) }}</span></div>
                            @foreach ($child->articles->take(3) as $preview)
                                <p class="mt-3 line-clamp-2 text-sm text-zinc-600"><span class="font-medium text-zinc-800">{{ $preview->title }}:</span> {{ $preview->excerptPreview() }}</p>
                            @endforeach
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mt-8">
            <h2 class="text-sm font-semibold uppercase text-zinc-500">Articles in this category</h2>
            <div class="mt-3 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
                @forelse ($articles as $article)
                    <article class="px-5 py-5"><a href="{{ route('knowledge.show', $article) }}" class="font-semibold hover:underline">{{ $article->title }}</a><p class="mt-2 line-clamp-2 text-sm leading-6 text-zinc-600">{{ $article->excerptPreview() }}</p></article>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-zinc-500">No visible articles in this category.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $articles->links() }}</div>
        </section>
    </section>
</x-layouts.app>
