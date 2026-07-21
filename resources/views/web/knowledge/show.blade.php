<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header :title="$article->title" :description="$article->categoryRecord?->path() ?? $article->category ?? 'Knowledge article'">
            <x-slot:action>
                <div class="flex items-center gap-2">
                    @can('update', $article)
                        <a href="{{ route('knowledge.versions', $article) }}" class="inline-flex items-center gap-2 rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold"><i data-lucide="history" class="h-4 w-4" aria-hidden="true"></i>Versions</a>
                        <a href="{{ route('knowledge.edit', $article) }}" class="inline-flex items-center gap-2 rounded-md bg-zinc-950 px-3 py-2 text-sm font-semibold text-white"><i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i>Edit</a>
                    @endcan
                </div>
            </x-slot:action>
        </x-page-header>

        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            <span class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 font-medium text-zinc-700">{{ str($article->status?->value)->title() }}</span>
            <span>Version {{ $article->version }}</span>
            <span aria-hidden="true">·</span>
            <span>Updated {{ $article->updated_at->diffForHumans() }}@if ($article->updater) by {{ $article->updater->name }}@endif</span>
            @foreach ($article->tags ?? [] as $tag)
                <span class="rounded-full bg-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-700">{{ $tag }}</span>
            @endforeach
        </div>

        @if ($article->excerpt)
            <p class="mt-5 max-w-4xl text-base leading-7 text-zinc-600">{{ $article->excerpt }}</p>
        @endif

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
            <article class="rounded-lg border border-zinc-200 bg-white px-6 py-7 shadow-sm sm:px-10 sm:py-10">
                <div class="knowledge-content">{!! $rendered['html'] !!}</div>
            </article>

            <aside class="space-y-4 lg:sticky lg:top-6">
                @if (count($rendered['headings']) >= 2)
                    <nav class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm" aria-label="On this page">
                        <h2 class="text-sm font-semibold">On this page</h2>
                        <ol class="mt-3 space-y-2 text-sm text-zinc-600">
                            @foreach ($rendered['headings'] as $heading)
                                <li class="{{ $heading['level'] === 3 ? 'pl-3' : '' }}"><a href="#{{ $heading['id'] }}" class="hover:text-zinc-950 hover:underline">{{ $heading['label'] }}</a></li>
                            @endforeach
                        </ol>
                    </nav>
                @endif

                <div class="rounded-lg border border-zinc-200 bg-white p-4 text-sm shadow-sm">
                    <h2 class="font-semibold">Article details</h2>
                    <dl class="mt-3 space-y-3 text-zinc-600">
                        <div><dt class="text-xs font-medium uppercase text-zinc-400">Created by</dt><dd class="mt-0.5">{{ $article->creator?->name ?? 'System' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-zinc-400">Published</dt><dd class="mt-0.5">{{ $article->published_at?->format('M j, Y') ?? 'Not published' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-zinc-400">Revisions</dt><dd class="mt-0.5">{{ $article->versions_count }}</dd></div>
                    </dl>
                </div>
            </aside>
        </div>

        <div class="mt-6">
            <x-attachments-panel :attachments="$article->attachments" attachable-type="knowledge_articles" :attachable-id="$article->id" />
        </div>

        @if ($article->outgoingLinks->isNotEmpty() || $article->incomingLinks->isNotEmpty())
            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm"><h2 class="font-semibold">Linked articles</h2><div class="mt-3 divide-y divide-zinc-100">@forelse ($article->outgoingLinks as $linked)<a href="{{ route('knowledge.show', $linked) }}" class="block py-3 first:pt-0 last:pb-0"><span class="font-medium hover:underline">{{ $linked->title }}</span><span class="mt-1 line-clamp-2 block text-sm text-zinc-500">{{ $linked->excerptPreview() }}</span></a>@empty<p class="text-sm text-zinc-500">No outgoing links.</p>@endforelse</div></section>
                <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm"><h2 class="font-semibold">Referenced by</h2><div class="mt-3 divide-y divide-zinc-100">@forelse ($article->incomingLinks as $linked)<a href="{{ route('knowledge.show', $linked) }}" class="block py-3 first:pt-0 last:pb-0"><span class="font-medium hover:underline">{{ $linked->title }}</span><span class="mt-1 line-clamp-2 block text-sm text-zinc-500">{{ $linked->excerptPreview() }}</span></a>@empty<p class="text-sm text-zinc-500">No backlinks.</p>@endforelse</div></section>
            </div>
        @endif
    </section>
</x-layouts.app>
