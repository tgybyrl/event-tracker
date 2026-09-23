@php
    // Same field classes as the user forms, so inputs look the same
    // everywhere in the panel.
    $fieldLabel = 'mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted';
    $field = 'w-full rounded-xl border border-hairline bg-surface px-3 py-2.5 text-sm font-semibold text-ink';
    $error = 'mt-1.5 text-sm font-semibold text-tag-rose-ink';
@endphp

<x-layouts.app title="Settings">
    <x-card class="max-w-2xl">
        {{-- No id in the action URL: the controller edits whoever is logged in. --}}
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="{{ $fieldLabel }}">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                       required autocomplete="name" class="{{ $field }}">
                @error('name') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            {{-- Shown, not editable. The email is the login, and changing it
                 is a manager's job on /users. Same for the role. --}}
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <p class="{{ $fieldLabel }}">Email</p>
                    <p class="py-2.5 text-sm font-semibold">{{ $user->email }}</p>
                </div>
                <div>
                    <p class="{{ $fieldLabel }}">Role</p>
                    <p class="py-2"><x-badge :tone="$user->isManager() ? 'blue' : 'slate'">{{ $user->role }}</x-badge></p>
                </div>
            </div>

            <div class="space-y-5 border-t border-hairline pt-5">
                <div>
                    <h2 class="text-base font-bold">Change password</h2>
                    <p class="mt-0.5 text-sm text-muted">Leave all three blank to keep your current password.</p>
                </div>

                <div>
                    <label for="current_password" class="{{ $fieldLabel }}">Current password</label>
                    <input type="password" id="current_password" name="current_password"
                           autocomplete="current-password" class="{{ $field }}">
                    @error('current_password') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="{{ $fieldLabel }}">New password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="new-password" class="{{ $field }}">
                    @error('password') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="{{ $fieldLabel }}">Confirm new password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           autocomplete="new-password" class="{{ $field }}">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 border-t border-hairline pt-5">
                <x-button type="submit" variant="primary">Save changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
