<x-layouts.app>
    <section class="mx-auto w-full max-w-7xl px-6 py-8">
        <x-page-header title="Knowledge setup" description="Manage hierarchical categories and reusable tags." />

        @if ($errors->any())
            <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
            <x-panel class="overflow-hidden !p-0">
                <div class="border-b border-zinc-200 px-5 py-4">
                    <h2 class="font-semibold">Categories</h2>
                    <form method="POST" action="{{ route('knowledge-taxonomy.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                        @csrf
                        <input type="hidden" name="type" value="category">
                        <label class="text-sm font-medium">New category<input name="name" required autocomplete="off" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                        <label class="text-sm font-medium">Parent<select name="parent_id" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"><option value="">None</option>@foreach ($categories as $option)<option value="{{ $option['category']->id }}">{{ $option['path'] }}</option>@endforeach</select></label>
                        <button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Add</button>
                    </form>
                </div>

                <div class="divide-y divide-zinc-100">
                    @forelse ($categories as $item)
                        <div class="px-5 py-4" style="padding-left: {{ 1.25 + ($item['depth'] * 1.25) }}rem">
                            <div class="flex items-center justify-between gap-3"><div class="min-w-0"><div class="truncate font-medium">{{ $item['category']->name }}</div><div class="mt-0.5 truncate text-xs text-zinc-500">{{ $item['path'] }} · {{ $item['article_count'] }} {{ Str::plural('article', $item['article_count']) }}</div></div>
                                <form method="POST" action="{{ route('knowledge-taxonomy.destroy') }}">@csrf @method('DELETE')<input type="hidden" name="type" value="category"><input type="hidden" name="category_id" value="{{ $item['category']->id }}"><button title="Delete {{ $item['category']->name }}" aria-label="Delete {{ $item['category']->name }}" class="rounded-md border border-red-200 p-2 text-red-700 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i></button></form>
                            </div>
                            <form method="POST" action="{{ route('knowledge-taxonomy.update') }}" class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                                @csrf @method('PUT')
                                <input type="hidden" name="type" value="category"><input type="hidden" name="category_id" value="{{ $item['category']->id }}">
                                <input name="name" value="{{ $item['category']->name }}" required aria-label="Rename {{ $item['category']->name }}" class="min-w-0 rounded-md border border-zinc-300 px-3 py-2 text-sm">
                                <select name="parent_id" aria-label="Parent for {{ $item['category']->name }}" class="min-w-0 rounded-md border border-zinc-300 px-3 py-2 text-sm"><option value="">No parent</option>@foreach ($categories as $option)@if ($option['category']->id !== $item['category']->id)<option value="{{ $option['category']->id }}" @selected($item['category']->parent_id === $option['category']->id)>{{ $option['path'] }}</option>@endif @endforeach</select>
                                <button class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Save</button>
                            </form>
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-zinc-500">No categories yet.</p>
                    @endforelse
                </div>
            </x-panel>

            <x-panel class="overflow-hidden !p-0">
                <div class="border-b border-zinc-200 px-5 py-4">
                    <h2 class="font-semibold">Tags</h2>
                    <form method="POST" action="{{ route('knowledge-taxonomy.store') }}" class="mt-4 flex items-end gap-2">@csrf<input type="hidden" name="type" value="tag"><label class="min-w-0 flex-1 text-sm font-medium">New tag<input name="name" required autocomplete="off" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Add</button></form>
                </div>
                <div class="divide-y divide-zinc-100">
                    @forelse ($tags as $item)
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3"><div class="min-w-0"><div class="truncate font-medium">{{ $item['name'] }}</div><div class="mt-0.5 text-xs text-zinc-500">{{ $item['article_count'] }} {{ Str::plural('article', $item['article_count']) }}</div></div><form method="POST" action="{{ route('knowledge-taxonomy.destroy') }}">@csrf @method('DELETE')<input type="hidden" name="type" value="tag"><input type="hidden" name="current_name" value="{{ $item['name'] }}"><button title="Delete {{ $item['name'] }}" aria-label="Delete {{ $item['name'] }}" class="rounded-md border border-red-200 p-2 text-red-700 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i></button></form></div>
                            <form method="POST" action="{{ route('knowledge-taxonomy.update') }}" class="mt-3 flex gap-2">@csrf @method('PUT')<input type="hidden" name="type" value="tag"><input type="hidden" name="current_name" value="{{ $item['name'] }}"><input name="name" value="{{ $item['name'] }}" required class="min-w-0 flex-1 rounded-md border border-zinc-300 px-3 py-2 text-sm"><button class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Rename</button></form>
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-zinc-500">No tags yet.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>
    </section>
</x-layouts.app>
