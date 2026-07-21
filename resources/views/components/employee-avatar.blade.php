@props(['employee', 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'h-9 w-9 text-xs',
        'md' => 'h-12 w-12 text-sm',
        'lg' => 'h-16 w-16 text-base',
    ];
    $class = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex '.$class.' shrink-0 items-center justify-center overflow-hidden rounded-full bg-zinc-900 font-semibold text-white ring-1 ring-zinc-200']) }}>
    @if ($employee->photo_path)
        <img src="{{ route('employees.photo', $employee) }}" alt="{{ $employee->full_name }}" class="h-full w-full object-cover">
    @else
        {{ $employee->initials ?: '—' }}
    @endif
</span>
