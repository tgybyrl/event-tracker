@php
    $fieldLabel = 'mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted';
    $field = 'w-full rounded-xl border border-hairline bg-surface px-3 py-2.5 text-sm font-semibold text-ink';
    $error = 'mt-1.5 text-sm font-semibold text-tag-rose-ink';

    // The dropdown is built from the model constant the FormRequest validates
    // against, so the two can never drift apart.
    $roles = \App\Models\User::ROLES;
@endphp

<x-layouts.app title="Add user">
    <x-slot:actions>
        <x-button :href="route('users.index')">Cancel</x-button>
    </x-slot:actions>

    <x-card class="max-w-2xl">
        {{-- POST to store. No @method here: create is the one write browsers
             can send natively. --}}
        <form method="POST" action="{{ route('users.store') }}" class="space-y-5 p-5 sm:p-6">
            {{-- Without @csrf every POST in this panel is a 419. The token is
                 what ties the request to this session's form. --}}
            @csrf

            <div>
                <label for="name" class="{{ $fieldLabel }}">Name</label>
                {{-- old() refills the form after a failed validation pass, so a
                     typo in one field does not wipe the other three. --}}
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       required autofocus autocomplete="name" class="{{ $field }}">
                @error('name') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="{{ $fieldLabel }}">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       required autocomplete="email" class="{{ $field }}">
                @error('email') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="role" class="{{ $fieldLabel }}">Role</label>
                <select id="role" name="role" required class="{{ $field }}">
                    @foreach ($roles as $role)
                        {{-- Defaults to worker, matching the column default:
                             a slip gives the least access, not the most. --}}
                        <option value="{{ $role }}" @selected(old('role', 'worker') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
                @error('role') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="{{ $fieldLabel }}">Password</label>
                {{-- Never refilled from old(): a password that survives a failed
                     submit is a password sitting in the rendered HTML. --}}
                <input type="password" id="password" name="password"
                       required autocomplete="new-password" class="{{ $field }}">
                <p class="mt-1.5 text-xs text-muted">At least 8 characters.</p>
                @error('password') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="{{ $fieldLabel }}">Confirm password</label>
                {{-- The name must be exactly password_confirmation: that is what
                     the `confirmed` rule looks for. --}}
                <input type="password" id="password_confirmation" name="password_confirmation"
                       required autocomplete="new-password" class="{{ $field }}">
            </div>

            <div class="flex flex-wrap items-center gap-2.5 border-t border-hairline pt-5">
                <x-button type="submit" variant="primary">Create user</x-button>
                <x-button :href="route('users.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
