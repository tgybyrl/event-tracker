<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * The route already carries `can:manage-users`. Repeating the Gate here
     * means the rules cannot be reached by a route that forgot the middleware
     * — the request validates who is asking, not just what they sent.
     */
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
            // unique:users,email — the column has a UNIQUE index, so without
            // this the insert dies with a 500 instead of a form error.
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // `confirmed` pairs this with the password_confirmation field.
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            // The column is a plain VARCHAR(20) and would happily store
            // 'superadmin'. This is the only thing keeping it to two values.
            'role' => ['required', Rule::in(User::ROLES)],
        ];
    }
}
