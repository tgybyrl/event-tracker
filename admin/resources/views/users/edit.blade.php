@php
    $fieldLabel = 'mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted';
    $field = 'w-full rounded-xl border border-hairline bg-surface px-3 py-2.5 text-sm font-semibold text-ink';
    $fieldDisabled = $field . ' cursor-not-allowed opacity-60';
    $error = 'mt-1.5 text-sm font-semibold text-tag-rose-ink';

    $roles = \App\Models\User::ROLES;

    // Editing your own account: the role field locks. A manager who demotes
    // themselves loses the screen they are standing on, and if they were the
    // only manager the panel has no way back in.
    $isSelf = $user->is(auth()->user());
@endphp

<x-layouts.app title="Edit user">
    <x-slot:actions>
        <x-button :href="route('users.index')">Cancel</x-button>
    </x-slot:actions>

    <x-card class="max-w-2xl">
        {{-- Browsers only send GET and POST, so the real verb goes in a hidden
             _method field and Laravel routes on that. --}}
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="{{ $fieldLabel }}">Name</label>
                {{-- old() first, the stored value second: after a failed submit
                     the form shows what was typed, not what is in the row. --}}
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                       required autofocus autocomplete="name" class="{{ $field }}">
                @error('name') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="{{ $fieldLabel }}">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                       required autocomplete="email" class="{{ $field }}">
                @error('email') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="role" class="{{ $fieldLabel }}">Role</label>
                <select id="role" name="role" required @disabled($isSelf)
                        class="{{ $isSelf ? $fieldDisabled : $field }}">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>
                    @endforeach
                </select>

                @if ($isSelf)
                    {{-- A disabled select submits nothing, and `role` is required
                         — so the value is resent in a hidden field. The controller
                         still rejects a changed role for your own row; this input
                         is reachable from devtools, the check is not. --}}
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <p class="mt-1.5 text-xs text-muted">You cannot change your own role.</p>
                @else
                    @error('role') <p class="{{ $error }}">{{ $message }}</p> @enderror
                @endif
            </div>

            <div>
                <label for="password" class="{{ $fieldLabel }}">New password</label>
                {{-- Optional here, unlike create: blank means keep the current
                     one. Nothing can read the existing password back, so there
                     is nothing to prefill. --}}
                <input type="password" id="password" name="password"
                       autocomplete="new-password" class="{{ $field }}">
                <p class="mt-1.5 text-xs text-muted">Leave blank to keep the current password.</p>
                @error('password') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="{{ $fieldLabel }}">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       autocomplete="new-password" class="{{ $field }}">
            </div>

            <div class="flex flex-wrap items-center gap-2.5 border-t border-hairline pt-5">
                <x-button type="submit" variant="primary">Save changes</x-button>
                <x-button :href="route('users.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
