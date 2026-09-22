<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Log a user in.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            // One message for both "no such email" and "wrong password".
            // Telling them apart would confirm which emails have accounts.
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // Session fixation: an attacker who plants a session id before login
        // would otherwise still hold a valid, now logged-in, id afterwards.
        $request->session()->regenerate();

        // intended() sends them back to the page the auth middleware bounced
        // them off, falling back to the dashboard on a direct visit.
        return redirect()->intended('/');
    }

    /**
     * Log the current user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        // Throw the session data away and issue a fresh CSRF token, so the old
        // session cookie is worthless if anyone still has it.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
