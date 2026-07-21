@props(['href', 'label'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'rounded-md border border-zinc-300 px-4 py-2 text-sm font-semibold']) }}>{{ $label }}</a>
