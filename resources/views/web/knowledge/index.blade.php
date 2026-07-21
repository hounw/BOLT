<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="Knowledge base" description="SOPs and operational knowledge for your team.">
            <x-slot:action>
                <div class="flex items-center gap-2"><a href="{{ route('knowledge-categories.index') }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Browse categories</a>@can('create', App\Models\KnowledgeArticle::class)<a href="{{ route('knowledge.create') }}" class="inline-flex items-center gap-2 rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white"><i data-lucide="file-plus" class="h-4 w-4" aria-hidden="true"></i>New article</a>@endcan</div>
            </x-slot:action>
        </x-page-header>

        <x-panel class="mt-6">
            <form method="GET" action="{{ route('knowledge.index') }}" class="grid gap-4 {{ $canManage ? 'md:grid-cols-4' : 'md:grid-cols-3' }}">
                <label class="block text-sm font-medium">Search<input name="q" value="{{ $filters['q'] ?? '' }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                @if ($canManage)
                    <label class="block text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"><option value="">Any status</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->title() }}</option>@endforeach</select></label>
                @endif
                <label class="block text-sm font-medium">Category<select name="category_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"><option value="">Any category</option>@foreach ($categories as $category)<option value="{{ $category['category']->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category['category']->id)>{{ $category['path'] }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium">Tag<select name="tag" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"><option value="">Any tag</option>@foreach ($tags as $tag)<option value="{{ $tag }}" @selected(($filters['tag'] ?? '') === $tag)>{{ $tag }}</option>@endforeach</select></label>
                @if ($canManage)<label class="flex items-center gap-2 text-sm font-medium md:col-span-full"><input type="checkbox" name="missing_excerpt" value="1" @checked($filters['missing_excerpt'] ?? false) class="rounded border-zinc-300">Missing curated excerpt</label>@endif
                <div class="flex items-end justify-end gap-3 md:col-span-full"><a href="{{ route('knowledge.index') }}" class="text-sm font-medium text-zinc-600">Clear</a><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Filter</button></div>
            </form>
        </x-panel>

        <div class="mt-6 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
            @forelse ($articles as $article)
                <article class="px-5 py-5 hover:bg-zinc-50">
                    <div class="flex items-start justify-between gap-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('knowledge.show', $article) }}" class="text-base font-semibold hover:underline">{{ $article->title }}</a>
                                @if ($canManage)<span class="rounded-full border border-zinc-200 px-2 py-0.5 text-xs font-medium text-zinc-600">{{ str($article->status?->value)->title() }}</span>@endif
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-zinc-600">{{ $article->excerptPreview() }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                <span>{{ $article->categoryRecord?->path() ?? $article->category ?? 'Uncategorized' }}</span><span>·</span><span>v{{ $article->version }}</span><span>·</span><span>{{ $article->updated_at->diffForHumans() }}</span>
                                @if ($article->outgoing_links_count || $article->incoming_links_count)<span>·</span><span>{{ $article->outgoing_links_count }} linked · {{ $article->incoming_links_count }} backlinks</span>@endif
                                @foreach ($article->tags ?? [] as $tag)<span class="rounded-full bg-zinc-100 px-2 py-1 text-zinc-600">{{ $tag }}</span>@endforeach
                            </div>
                        </div>
                        @can('update', $article)<a href="{{ route('knowledge.edit', $article) }}" class="shrink-0 text-sm font-medium text-zinc-600 hover:text-zinc-950">Edit</a>@endcan
                    </div>
                </article>
            @empty
                <div class="px-6 py-16 text-center"><p class="font-medium">No articles found</p><p class="mt-1 text-sm text-zinc-500">Try clearing the filters or create the first article.</p></div>
            @endforelse
        </div>

        <div class="mt-4">{{ $articles->links() }}</div>
    </section>
</x-layouts.app>
