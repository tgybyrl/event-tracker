@props(['title' => 'Sign in'])

{{-- A second layout, not a variant of layouts/app: that one is a sidebar plus a
     topbar, and neither means anything to someone who is not logged in yet. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-canvas font-sans text-ink antialiased">
    <main class="flex min-h-full items-center justify-center px-5 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex items-center justify-center gap-2.5">
                {{-- Same mark as the sidebar, so the login page belongs to the
                     same product. --}}
                <svg viewBox="0 0 24 24" class="size-6 shrink-0" aria-hidden="true">
                    <rect x="2" y="13" width="5" height="9" rx="2.5" fill="#a9c3f6"/>
                    <rect x="9.5" y="7" width="5" height="15" rx="2.5" fill="#5a8cef"/>
                    <rect x="17" y="2" width="5" height="20" rx="2.5" fill="#2e6be6"/>
                </svg>
                <span class="text-[17px] font-extrabold tracking-tight">{{ config('app.name') }}</span>
            </div>

            {{ $slot }}
        </div>
    </main>
</body>
</html>
