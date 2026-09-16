@php
    // Each item: label, href, the URL patterns that light it up, and its icon
    // paths (drawn inline so the panel needs no icon package).
    $nav = [
        [
            'label' => 'Dashboard',
            'href' => '/',
            'active' => ['/'],
            'icon' => '<rect x="3" y="3" width="7.5" height="7.5" rx="2.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2.5"/>',
        ],
        [
            'label' => 'Events',
            'href' => '/events',
            'active' => ['events', 'events/*'],
            'icon' => '<path d="M3 12h3.5l2.5-7 4 14 2.5-7H21"/>',
        ],
        [
            'label' => 'Users',
            'href' => '/users',
            'active' => ['users', 'users/*'],
            'icon' => '<path d="M15.5 20v-1.5a3.5 3.5 0 0 0-3.5-3.5H7a3.5 3.5 0 0 0-3.5 3.5V20"/><circle cx="9.5" cy="8" r="3.5"/><path d="M20.5 20v-1.5a3.5 3.5 0 0 0-2.7-3.4"/><path d="M15.5 4.7a3.5 3.5 0 0 1 0 6.6"/>',
        ],
        [
            'label' => 'Settings',
            'href' => '/settings',
            'active' => ['settings', 'settings/*'],
            'icon' => '<path d="M4 7h7.5M16.5 7H20M4 17h3.5M12.5 17H20"/><circle cx="14" cy="7" r="2.5"/><circle cx="10" cy="17" r="2.5"/>',
        ],
    ];
@endphp

<aside id="sidebar"
       class="fixed inset-y-0 start-0 z-50 flex w-[248px] -translate-x-full flex-col border-e border-hairline bg-surface transition-transform duration-200 lg:static lg:translate-x-0">
    <div class="flex h-[72px] shrink-0 items-center gap-2.5 px-6">
        {{-- Rising bars: event volume over time. The only mark in the panel. --}}
        <svg viewBox="0 0 24 24" class="size-6 shrink-0" aria-hidden="true">
            <rect x="2" y="13" width="5" height="9" rx="2.5" fill="#a9c3f6"/>
            <rect x="9.5" y="7" width="5" height="15" rx="2.5" fill="#5a8cef"/>
            <rect x="17" y="2" width="5" height="20" rx="2.5" fill="#2e6be6"/>
        </svg>
        <span class="text-[17px] font-extrabold tracking-tight">{{ config('app.name') }}</span>

        <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false"
                class="ms-auto rounded-lg p-1.5 text-muted hover:bg-canvas hover:text-ink lg:hidden">
            <span class="sr-only">Close navigation</span>
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.75"
                 stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-2" aria-label="Main">
        @foreach ($nav as $item)
            @php $isActive = request()->is($item['active']); @endphp
            <a href="{{ $item['href'] }}"
               @if ($isActive) aria-current="page" @endif
               class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-[15px] font-semibold transition-colors
                      {{ $isActive ? 'bg-brand text-white' : 'text-muted hover:bg-canvas hover:text-ink' }}">
                <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-hairline p-4">
        {{-- TODO(phase E): POST to a logout route once auth exists. --}}
        <a href="/logout"
           class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-[15px] font-semibold text-muted transition-colors hover:bg-canvas hover:text-ink">
            <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9.5 21H5.5A2.5 2.5 0 0 1 3 18.5v-13A2.5 2.5 0 0 1 5.5 3h4"/><path d="m16 16.5 4.5-4.5L16 7.5"/><path d="M20.5 12H9.5"/>
            </svg>
            Log out
        </a>
    </div>
</aside>
