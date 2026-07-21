@php
    $restoring = isset($restoreVersion) && $restoreVersion;
    $title = old('title', $restoring ? $restoreVersion->title : $article->title);
    $body = old('body_markdown', $restoring ? $restoreVersion->body_markdown : $article->body_markdown);
    $excerpt = old('excerpt', $restoring ? $restoreVersion->excerpt : $article->excerpt);
    $categoryId = old('category_id', $restoring ? $restoreVersion->category_id : $article->category_id);
    $selectedCategory = collect($categories)->first(fn ($item) => (string) $item['category']->id === (string) $categoryId);
    $categoryPath = $selectedCategory['path'] ?? '';
    $selectedTagValues = old('tags', $restoring ? ($restoreVersion->tags ?? []) : ($article->tags ?? []));
    $selectedTags = collect(is_array($selectedTagValues) ? $selectedTagValues : explode(',', (string) $selectedTagValues))
        ->flatMap(fn ($tag) => explode(',', (string) $tag))
        ->map(fn ($tag) => trim($tag))
        ->filter();
@endphp

<x-layouts.app>
    <section class="mx-auto w-full max-w-6xl px-6 py-8">
        <x-page-header
            :title="$article->exists ? ($restoring ? 'Restore version '.$restoreVersion->version : 'Edit article') : 'New article'"
            :description="$restoring ? 'Review the earlier content before saving it as a new version.' : null"
        />

        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ $article->exists ? route('knowledge.update', $article) : route('knowledge.store') }}"
            class="mt-6 space-y-6"
            data-markdown-editor
            data-preview-url="{{ route('knowledge.preview') }}"
            data-link-search-url="{{ $linkSearchUrl }}"
            data-current-article-id="{{ $article->id }}"
        >
            @csrf
            @if ($article->exists)
                @method('PUT')
            @endif
            @if ($restoring)
                <input type="hidden" name="restored_from_version" value="{{ $restoreVersion->version }}">
            @endif

            <x-panel>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium">Title<input name="title" value="{{ $title }}" required data-markdown-title class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Slug<input name="slug" value="{{ old('slug', $article->slug) }}" placeholder="Generated from title" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <div class="block text-sm font-medium" data-single-combobox>
                        <div class="flex items-center justify-between gap-3"><label for="knowledge-category-search">Category</label><a href="{{ route('knowledge-taxonomy.index') }}" class="text-xs font-medium text-zinc-500 hover:text-zinc-950">Manage</a></div>
                        <input type="hidden" name="category_id" value="{{ $categoryId }}" data-combobox-value>
                        <div class="relative mt-1">
                            <input id="knowledge-category-search" value="{{ $categoryPath }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="knowledge-category-options" placeholder="Search categories" data-combobox-search class="w-full rounded-md border border-zinc-300 py-2 pl-3 pr-10 font-normal">
                            <button type="button" data-combobox-toggle title="Show categories" aria-label="Show categories" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-zinc-500"><i data-lucide="chevron-down" class="h-4 w-4" aria-hidden="true"></i></button>
                            <div id="knowledge-category-options" role="listbox" data-combobox-menu hidden class="absolute z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-zinc-200 bg-white p-1 shadow-lg">
                                <button type="button" role="option" data-combobox-option data-value="" data-label="No category" class="block w-full rounded px-3 py-2 text-left text-sm font-normal hover:bg-zinc-100">No category</button>
                                @foreach ($categories as $item)
                                    <button type="button" role="option" data-combobox-option data-value="{{ $item['category']->id }}" data-label="{{ $item['path'] }}" class="block w-full rounded px-3 py-2 text-left text-sm font-normal hover:bg-zinc-100">{{ $item['path'] }}</button>
                                @endforeach
                                <p data-combobox-empty hidden class="px-3 py-4 text-center text-sm font-normal text-zinc-500">No matching categories.</p>
                            </div>
                        </div>
                    </div>
                    <label class="block text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $article->status?->value ?? 'draft') === $status->value)>{{ str($status->value)->title() }}</option>@endforeach</select></label>
                </div>

                <label class="mt-4 block text-sm font-medium">
                    <span class="flex items-center justify-between gap-3"><span>Excerpt</span><span data-character-count class="text-xs font-normal text-zinc-500">{{ mb_strlen((string) $excerpt) }}/300</span></span>
                    <textarea name="excerpt" maxlength="300" rows="3" data-character-limit="300" class="mt-1 w-full resize-y rounded-md border border-zinc-300 px-3 py-2">{{ $excerpt }}</textarea>
                </label>

                <div class="mt-4" data-multi-combobox>
                    <div class="flex items-center justify-between gap-3"><label for="knowledge-tag-search" class="text-sm font-medium">Tags</label><a href="{{ route('knowledge-taxonomy.index') }}" class="text-xs font-medium text-zinc-500 hover:text-zinc-950">Manage</a></div>
                    <div class="relative mt-1">
                        <div data-combobox-control class="flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-md border border-zinc-300 bg-white px-2 py-1.5 focus-within:border-zinc-500 focus-within:ring-1 focus-within:ring-zinc-500">
                            <div data-combobox-selected class="contents">
                                @foreach ($selectedTags as $tag)
                                    <span data-selected-value="{{ $tag }}" class="inline-flex max-w-full items-center gap-1 rounded bg-zinc-100 px-2 py-1 text-sm text-zinc-700"><span class="truncate">{{ $tag }}</span><button type="button" data-remove-value="{{ $tag }}" aria-label="Remove {{ $tag }}" class="text-zinc-400 hover:text-zinc-950">×</button></span>
                                @endforeach
                            </div>
                            <input id="knowledge-tag-search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="knowledge-tag-options" placeholder="Search tags" data-combobox-search class="min-w-32 flex-1 border-0 bg-transparent px-1 py-1 text-sm outline-none">
                            <button type="button" data-combobox-toggle title="Show tags" aria-label="Show tags" class="flex h-7 w-7 shrink-0 items-center justify-center text-zinc-500"><i data-lucide="chevron-down" class="h-4 w-4" aria-hidden="true"></i></button>
                        </div>
                        <div id="knowledge-tag-options" role="listbox" aria-multiselectable="true" data-combobox-menu hidden class="absolute z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-zinc-200 bg-white p-1 shadow-lg">
                            @foreach ($tags as $tag)
                                <button type="button" role="option" data-combobox-option data-value="{{ $tag }}" data-label="{{ $tag }}" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-zinc-100">{{ $tag }}</button>
                            @endforeach
                            <p data-combobox-empty hidden class="px-3 py-4 text-center text-sm text-zinc-500">No matching tags.</p>
                        </div>
                    </div>
                    <div data-combobox-values>
                        @foreach ($selectedTags as $tag)<input type="hidden" name="tags[]" value="{{ $tag }}">@endforeach
                    </div>
                </div>
            </x-panel>

            @unless ($article->exists)
                <x-panel>
                    <label class="block text-sm font-medium">
                        Import Markdown
                        <input name="source_markdown" type="file" accept=".md,.markdown,text/markdown,text/plain" data-markdown-import class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                    </label>
                    <p class="mt-2 text-xs text-zinc-500" data-import-status>The source file will be retained with the article.</p>
                </x-panel>
            @endunless

            <x-panel class="overflow-hidden !p-0">
                <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 px-4 py-3">
                    <div class="inline-flex rounded-md border border-zinc-300 bg-white p-0.5" role="tablist" aria-label="Article mode">
                        <button type="button" role="tab" data-markdown-tab="write" aria-controls="knowledge-write-panel" aria-selected="true" class="rounded px-3 py-1.5 text-sm font-semibold text-zinc-950 shadow-sm">Write</button>
                        <button type="button" role="tab" data-markdown-tab="preview" aria-controls="knowledge-preview-panel" aria-selected="false" class="rounded px-3 py-1.5 text-sm font-medium text-zinc-500">Preview</button>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-zinc-500">
                        <span>Markdown</span>
                        <button type="button" data-dialog-open="markdown-help" title="Markdown syntax help" aria-label="Open Markdown syntax help" class="rounded p-1 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-950">
                            <i data-lucide="circle-help" class="h-4 w-4" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div id="knowledge-write-panel" role="tabpanel" data-markdown-write class="relative">
                    <label for="body_markdown" class="sr-only">Markdown body</label>
                    <textarea id="body_markdown" name="body_markdown" rows="24" required data-markdown-body class="block w-full resize-y border-0 px-5 py-4 font-mono text-sm leading-6 outline-none">{{ $body }}</textarea>
                    <div data-article-link-menu hidden class="absolute left-5 right-5 top-16 z-30 max-h-64 overflow-y-auto rounded-md border border-zinc-200 bg-white p-1 shadow-lg"></div>
                </div>
                <div id="knowledge-preview-panel" role="tabpanel" data-markdown-preview hidden class="min-h-96 px-6 py-6">
                    <div data-markdown-preview-content class="knowledge-content"></div>
                    <p data-markdown-preview-empty class="text-sm text-zinc-500">Nothing to preview yet.</p>
                </div>
            </x-panel>

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="flex justify-end gap-3">
                <a href="{{ $article->exists ? route('knowledge.show', $article) : route('knowledge.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Cancel</a>
                <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">{{ $restoring ? 'Save as new version' : 'Save' }}</button>
            </div>
        </form>

        <dialog data-dialog="markdown-help" aria-labelledby="markdown-help-title" class="modal-dialog modal-dialog-wide">
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
                <div>
                    <h2 id="markdown-help-title" class="font-semibold">Markdown cheatsheet</h2>
                    <p class="mt-1 text-sm text-zinc-500">Syntax supported by the BOLT article renderer.</p>
                </div>
                <button type="button" data-dialog-close="markdown-help" title="Close" aria-label="Close Markdown syntax help" class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-950">
                    <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                </button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-5 py-5">
                <div class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                    <div><h3 class="text-sm font-semibold">Headings</h3><pre class="mt-2 overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs leading-5 text-zinc-100"><code># Heading 1
## Heading 2
### Heading 3</code></pre></div>
                    <div><h3 class="text-sm font-semibold">Emphasis</h3><pre class="mt-2 overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs leading-5 text-zinc-100"><code>**bold text**
*italic text*
~~strikethrough~~</code></pre></div>
                    <div><h3 class="text-sm font-semibold">Lists</h3><pre class="mt-2 overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs leading-5 text-zinc-100"><code>- First item
- Second item

1. First step
2. Second step</code></pre></div>
                    <div><h3 class="text-sm font-semibold">Task list</h3><pre class="mt-2 overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs leading-5 text-zinc-100"><code>- [x] Complete
- [ ] Still open</code></pre></div>
                    <div><h3 class="text-sm font-semibold">Links and quotes</h3><pre class="mt-2 overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs leading-5 text-zinc-100"><code>@Article title
[Link label](https://example.com)

&gt; Important note</code></pre></div>
                    <div><h3 class="text-sm font-semibold">Code</h3><pre class="mt-2 overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs leading-5 text-zinc-100"><code>`inline code`

```php
echo 'Hello';
```</code></pre></div>
                    <div class="sm:col-span-2"><h3 class="text-sm font-semibold">Table</h3><pre class="mt-2 overflow-x-auto rounded-md bg-zinc-950 p-3 text-xs leading-5 text-zinc-100"><code>| Name | Status |
| --- | --- |
| VPN | Ready |</code></pre></div>
                </div>
            </div>

            <div class="flex justify-end border-t border-zinc-200 px-5 py-4">
                <button type="button" data-dialog-close="markdown-help" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Done</button>
            </div>
        </dialog>
    </section>
</x-layouts.app>
