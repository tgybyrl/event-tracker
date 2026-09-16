<?php

use Illuminate\Support\Facades\Route;

// Phase A plumbing: renders the shell with no data behind it.
// Phase B replaces this with a controller.
Route::view('/', 'dashboard');
