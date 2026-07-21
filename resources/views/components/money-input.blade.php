@props([
    'currency' => 'USD',
    'symbol' => null,
    'name' => 'amount',
    'value' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $currencySymbol = $symbol ?? $currency;
@endphp

<div class="relative mt-1">
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center border-r border-zinc-200 bg-zinc-50 px-3 text-sm font-semibold text-zinc-600">{{ $currencySymbol }}</span>
    <input
        name="{{ $name }}"
        type="number"
        min="0"
        step="0.01"
        value="{{ $value }}"
        @required($required)
        @disabled($disabled)
        {{ $attributes->merge(['class' => 'w-full rounded-md border border-zinc-300 py-2 pl-12 pr-3']) }}
    >
</div>
