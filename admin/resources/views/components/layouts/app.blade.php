@props(['title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-canvas font-sans text-ink antialiased">
    <a href="#content"
       class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-60 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to content
    </a>

    <div class="lg:grid lg:min-h-full lg:grid-cols-[248px_1fr]">
        <div id="sidebar-backdrop" hidden
             class="fixed inset-0 z-40 bg-ink/35 lg:hidden"></div>

        <x-partials.sidebar />

        <div class="flex min-h-screen min-w-0 flex-col">
            <x-partials.topbar />

            <main id="content" class="min-w-0 flex-1 px-5 py-6 sm:px-8 sm:py-7">
                <header class="mb-6 flex flex-wrap items-center gap-x-4 gap-y-3">
                    <h1 class="text-2xl font-bold tracking-tight sm:text-[28px]">{{ $title }}</h1>
                    @isset($actions)
                        <div class="ms-auto flex items-center gap-2.5">{{ $actions }}</div>
                    @endisset
                </header>

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
