@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
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

    {{-- The notifications bell was dropped for the same reason: nothing
         produces notifications, so it was a button that could never do
         anything. It also carried the ms-auto that pushed this bar's contents
         right, which is why the chip below owns that class now. --}}

    {{-- A label, not a control. It was a <button> with a chevron, which
         promised a dropdown that never existed — same reason the free-text
         search left this bar in phase B. If a profile menu is ever built, this
         goes back to being a <button>. --}}
    <div class="ms-auto flex shrink-0 items-center gap-2.5 rounded-full p-1">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-tint text-[13px] font-bold text-brand-ink">
            {{ $initials }}
        </span>
        <span class="hidden text-start leading-tight md:block">
            <span class="block text-sm font-bold">{{ $user->name }}</span>
            <span class="block text-xs text-muted">{{ $user->email }}</span>
        </span>
    </div>
</header>
