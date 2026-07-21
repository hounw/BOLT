@props(['asset', 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'h-12 w-16 text-xs',
        'md' => 'h-24 w-32 text-sm',
        'lg' => 'h-48 w-full text-base',
    ];
    $class = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex '.$class.' shrink-0 items-center justify-center overflow-hidden rounded-md bg-zinc-100 font-semibold text-zinc-500 ring-1 ring-zinc-200']) }}>
    @if ($asset->photo_path)
        <img src="{{ route('assets.photo', $asset) }}" alt="{{ $asset->asset_tag }} {{ $asset->name }}" class="h-full w-full object-cover">
    @else
        {{ $asset->asset_tag ?: 'Asset' }}
    @endif
</div>
