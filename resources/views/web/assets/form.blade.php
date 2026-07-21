<x-layouts.app>
    <section class="mx-auto w-full max-w-3xl px-6 py-8">
        <x-page-header :title="$asset->exists ? 'Edit asset' : 'New asset'" />
        <x-panel class="mt-6">
            <form method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}" enctype="multipart/form-data" class="grid gap-5">
                @csrf
                @if ($asset->exists)
                    @method('PUT')
                @endif
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2" data-repeatable-photos>
                        <label class="block text-sm font-medium">Asset photos</label>
                        <div class="mt-1 grid gap-2" data-photo-inputs>
                            <input name="photos[]" type="file" accept="image/*" class="w-full rounded-md border border-zinc-300 px-3 py-2" data-photo-input>
                        </div>
                        <button type="button" class="mt-2 rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold" data-add-photo>Add another photo</button>
                        <p class="mt-1 text-xs text-zinc-500">Add as many images as needed. The first image becomes the primary photo and all images are saved as private asset files.</p>
                        @if ($asset->photo_path)
                            <label class="mt-3 flex items-center gap-2 text-sm font-medium">
                                <input name="remove_photo" type="checkbox" value="1" class="rounded border-zinc-300">
                                Remove current asset photo
                            </label>
                        @endif
                    </div>
                    <label class="block text-sm font-medium">Name<input name="name" value="{{ old('name', $asset->name) }}" required class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">
                        Tags
                        <input name="tags" value="{{ old('tags', collect($asset->tags ?? [])->implode(', ')) }}" list="asset-tag-options" placeholder="Laptop, Sales, Apple" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2" data-tag-input>
                        <span class="mt-1 block text-xs font-normal text-zinc-500">Comma-separated reusable tags.</span>
                        <span class="mt-2 flex flex-wrap gap-2" data-tag-suggestions>
                            @foreach ($assetTags as $tag)
                                <button type="button" class="rounded-full border border-zinc-300 px-2.5 py-1 text-xs font-semibold text-zinc-700" data-tag-suggestion="{{ $tag }}">{{ $tag }}</button>
                            @endforeach
                        </span>
                    </label>
                    <datalist id="asset-tag-options">
                        @foreach ($assetTags as $tag)
                            <option value="{{ $tag }}"></option>
                        @endforeach
                    </datalist>
                    <label class="block text-sm font-medium">Serial<input name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Status<select name="status" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $asset->status?->value ?? 'available') === $status->value)>{{ str($status->value)->title() }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Vendor<input name="vendor" value="{{ old('vendor', $asset->vendor) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Purchase date<input name="purchase_date" type="date" value="{{ old('purchase_date', $asset->purchase_date?->toDateString()) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Warranty expires<input name="warranty_expires_on" type="date" value="{{ old('warranty_expires_on', $asset->warranty_expires_on?->toDateString()) }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"></label>
                    <label class="block text-sm font-medium">Cost<x-money-input name="purchase_cost" :value="old('purchase_cost', $asset->purchase_cost)" :currency="$mainCurrency" :symbol="$mainCurrencySymbol" /></label>
                </div>
                @if ($errors->any())<div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                <div class="flex justify-end gap-3"><a href="{{ route('assets.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">Save</button></div>
            </form>
        </x-panel>
    </section>
</x-layouts.app>
