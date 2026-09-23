<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Your own account, from /settings. Deliberately has no `role` and no
 * `email` rule: validated() only returns keys named here, so a hand-crafted
 * PUT carrying role=manager never reaches the update.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * No Gate: every logged-in user may edit their own account, and the
     * route's `auth` middleware already guarantees there is one. What keeps
     * this safe is that the controller only ever edits $request->user().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Required only when a new password is being set. Without it, a
            // borrowed unlocked laptop becomes a permanent account takeover.
            // `current_password` is Laravel's own rule: it checks the value
            // against the logged-in user's stored hash.
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            // Same as UpdateUserRequest: blank means keep the current one.
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
