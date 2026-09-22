<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// 'guest' bounces an already-logged-in user away from the login form.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');

    // throttle:5,1 = five attempts a minute per IP. Without it the form is an
    // open password oracle.
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
});

// POST, not GET: a GET /logout can be fired by any <img> tag on a page the
// user happens to visit.
Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Everything below needs a session. The 'auth' middleware redirects to the
// route *named* login, which is why the name above matters.
Route::middleware('auth')->group(function () {
    // Phase A plumbing: renders the shell with no data behind it.
    // A later pass replaces this with a controller that fills the stat cards.
    Route::view('/', 'dashboard');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');

    // One line for six routes: index/create/store/edit/update/destroy, all
    // behind the same Gate. `show` is excluded — a read-only page for four
    // fields the list already prints would be a screen with nothing on it.
    Route::resource('users', UserController::class)
        ->except('show')
        ->middleware('can:manage-users');
});
