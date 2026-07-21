@props(['item', 'level' => 0])

@php
    $user = auth()->user();
    $canSee = function (array $candidate, int $candidateLevel = 0) use (&$canSee, $user): bool {
        $permission = $candidate['can'] ?? null;
        $selfVisible = blank($permission) || $user?->can($permission);

        if ($selfVisible) {
            return true;
        }

        if ($candidateLevel >= 2) {
            return false;
        }

        return collect($candidate['children'] ?? [])->contains(fn (array $child): bool => $canSee($child, $candidateLevel + 1));
    };
    $itemHref = function (array $candidate) use (&$itemHref): string {
        return isset($candidate['route']) ? route($candidate['route']) : ($candidate['url'] ?? '#');
    };
    $firstVisibleHref = function (array $candidate, int $candidateLevel = 0) use (&$firstVisibleHref, $canSee, $itemHref): string {
        $permission = $candidate['can'] ?? null;

        if (blank($permission) || auth()->user()?->can($permission)) {
            return $itemHref($candidate);
        }

        if ($candidateLevel >= 2) {
            return $itemHref($candidate);
        }

        foreach ($candidate['children'] ?? [] as $child) {
            if ($canSee($child, $candidateLevel + 1)) {
                return $firstVisibleHref($child, $candidateLevel + 1);
            }
        }

        return $itemHref($candidate);
    };
    $children = $level < 2
        ? collect($item['children'] ?? [])->filter(fn (array $child): bool => $canSee($child, $level + 1))->values()->all()
        : [];
    $hasChildren = filled($children);
    $href = $firstVisibleHref($item, $level);
    $isSelfActive = isset($item['route']) ? request()->routeIs(str($item['route'])->beforeLast('.')->append('.*')->toString()) : request()->is(trim($item['url'] ?? '', '/'));
    $isChildActive = collect($children)->contains(function (array $child): bool {
        return isset($child['route'])
            ? request()->routeIs(str($child['route'])->beforeLast('.')->append('.*')->toString())
            : request()->is(trim($child['url'] ?? '', '/'));
    });
    $isActive = $isSelfActive || $isChildActive;
    $submenuClass = $level === 0
        ? 'nav-submenu left-0 top-full min-w-56 pt-2'
        : 'nav-submenu left-full top-0 min-w-56 pl-2';
@endphp

@if (! $canSee($item, $level))
    @php return; @endphp
@endif

<div class="nav-item relative">
    <a
        href="{{ $href }}"
        class="{{ $level === 0 ? 'nav-top-link' : 'nav-menu-link' }} {{ $isActive ? 'text-zinc-950' : '' }}"
        @if ($hasChildren) aria-haspopup="true" @endif
    >
        <span>{{ $item['label'] }}</span>
        @if ($hasChildren)
            <i data-lucide="{{ $level === 0 ? 'chevron-down' : 'chevron-right' }}" class="nav-icon" aria-hidden="true"></i>
        @endif
    </a>

    @if ($hasChildren)
        <div class="{{ $submenuClass }}">
            <div class="nav-submenu-panel">
                @foreach ($children as $child)
                    <x-nav-link :item="$child" :level="$level + 1" />
                @endforeach
            </div>
        </div>
    @endif
</div>
