@php
    // Same field classes as the phase C filter panel, so inputs look the same
    // everywhere in the panel.
    $fieldLabel = 'mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted';
    $field = 'w-full rounded-xl border border-hairline bg-surface px-3 py-2.5 text-sm font-semibold text-ink';
@endphp

<x-layouts.auth title="Sign in">
    <x-card class="p-6">
        <h1 class="text-xl font-bold tracking-tight">Sign in</h1>
        <p class="mt-1 text-sm text-muted">Use your panel account.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="{{ $fieldLabel }}">Email</label>
                {{-- old() keeps the email after a failed attempt: retyping it is
                     pure punishment for getting the password wrong. --}}
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       required autofocus autocomplete="username" class="{{ $field }}">
            </div>

            <div>
                <label for="password" class="{{ $fieldLabel }}">Password</label>
                {{-- Never repopulated, even on failure. --}}
                <input type="password" id="password" name="password"
                       required autocomplete="current-password" class="{{ $field }}">
            </div>

            @if ($errors->any())
                <ul class="space-y-1 text-sm font-semibold text-tag-rose-ink">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif

            <x-button type="submit" variant="primary" class="w-full justify-center">Sign in</x-button>
        </form>
    </x-card>
</x-layouts.auth>
