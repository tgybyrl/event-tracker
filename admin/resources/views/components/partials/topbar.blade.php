@php
    // TODO(phase E): replace with auth()->user() once login exists.
    $user = ['name' => 'Tugay Bakırlı', 'email' => 'tgybyrl48@gmail.com'];
    $initials = collect(explode(' ', $user['name']))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp

<header class="sticky top-0 z-30 flex h-[72px] shrink-0 items-center gap-3 border-b border-hairline bg-surface px-5 sm:px-8">
    <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false"
            class="-ms-1 rounded-lg p-2 text-muted hover:bg-canvas hover:text-ink lg:hidden">
        <span class="sr-only">Open navigation</span>
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.75"
             stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>

    {{-- Free-text search was dropped: nothing read ?q=, and the events table is
         better narrowed by its known columns. Phase C puts a Filter control on
         the events page instead. --}}

    <button type="button"
            class="relative ms-auto hidden rounded-full p-2.5 text-muted hover:bg-canvas hover:text-ink sm:block">
        <span class="sr-only">Notifications</span>
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.75"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8.5a6 6 0 1 0-12 0c0 6-2.5 8-2.5 8h17S18 14.5 18 8.5"/><path d="M13.7 20.5a2 2 0 0 1-3.4 0"/>
        </svg>
    </button>

    <button type="button" class="flex shrink-0 items-center gap-2.5 rounded-full p-1 hover:bg-canvas ms-auto sm:ms-0">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-tint text-[13px] font-bold text-brand-ink">
            {{ $initials }}
        </span>
        <span class="hidden text-start leading-tight md:block">
            <span class="block text-sm font-bold">{{ $user['name'] }}</span>
            <span class="block text-xs text-muted">{{ $user['email'] }}</span>
        </span>
        <svg viewBox="0 0 24 24" class="size-4 shrink-0 text-muted" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>
</header>
