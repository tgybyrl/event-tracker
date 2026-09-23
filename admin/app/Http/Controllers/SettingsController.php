<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The logged-in user's own account: name and password, nothing else.
 *
 * Neither method takes a user id. The account is always $request->user(),
 * so there is no URL to change to reach someone else's row — which is what
 * makes it safe to leave this screen without the manage-users Gate.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        // only() rather than validated(): current_password was needed to pass
        // validation but is not a column, and must never be written.
        $data = $request->safe()->only(['name', 'password']);

        // Blank password field = keep the current password. Same reason as
        // UserController: the 'hashed' cast would store a hash of "".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $request->user()->update($data);

        return redirect()
            ->route('settings.edit')
            ->with('status', isset($data['password']) ? 'Saved. Your password has changed.' : 'Saved.');
    }
}
