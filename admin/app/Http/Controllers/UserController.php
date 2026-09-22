<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Panel accounts. Reached only through `can:manage-users` on the route, so
 * every method here can assume the caller is a manager.
 *
 * Two guards run in this class rather than in the FormRequests, because both
 * depend on *which row* is being changed, not on the submitted values: a
 * manager may not demote or delete themselves. Together they also mean the
 * panel can never end up with zero managers — the last manager standing is
 * always the one holding the keyboard.
 */
class UserController extends Controller
{
    /**
     * Every panel account, alphabetically.
     */
    public function index(): View
    {
        $users = User::query()
            ->orderBy('name')
            // Tie-break for names that repeat, same reason as the events list.
            ->orderBy('id')
            ->paginate(25);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        // validated() returns only the keys the rules named, so a hand-crafted
        // POST carrying id=1 or role=owner cannot reach the insert.
        // The 'hashed' cast on the model hashes the password on save.
        $user = User::create($request->validated());

        return redirect()
            ->route('users.index')
            ->with('status', "Created {$user->name}.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // is() compares the model and its key, not just the id.
        if ($user->is($request->user()) && $data['role'] !== $user->role) {
            return back()
                ->withInput()
                ->with('error', 'You cannot change your own role. Ask another manager to do it.');
        }

        // Blank password field = keep the current password. Without this the
        // 'hashed' cast would happily store a hash of the empty string.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('status', "Updated {$user->name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', "Deleted {$user->name}.");
    }
}
