<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Same unique rule as the store request, minus this user's own row
            // — otherwise saving the form without touching the email fails on
            // the address the user already has.
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            // nullable, not required: an empty password field means "leave the
            // current password alone". The controller drops the key.
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(User::ROLES)],
        ];
    }
}
