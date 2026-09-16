@props(['title', 'body'])

{{-- An empty screen is an invitation to act, so it always names the next step. --}}
<div class="px-6 py-16 text-center">
    <svg viewBox="0 0 24 24" class="mx-auto size-8 text-muted" fill="none" stroke="currentColor"
         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 12h3.5l2.5-7 4 14 2.5-7H21"/>
    </svg>
    <p class="mt-4 text-base font-bold">{{ $title }}</p>
    <p class="mx-auto mt-1.5 max-w-sm text-sm text-muted">{{ $body }}</p>
    @isset($action)
        <div class="mt-5 flex justify-center">{{ $action }}</div>
    @endisset
</div>
