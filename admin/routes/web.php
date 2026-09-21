<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

// Phase A plumbing: renders the shell with no data behind it.
// A later pass replaces this with a controller that fills the stat cards.
Route::view('/', 'dashboard');

Route::get('/events', [EventController::class, 'index'])->name('events.index');
