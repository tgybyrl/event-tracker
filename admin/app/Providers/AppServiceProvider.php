<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One definition, three consumers: 'can:manage-users' on the route,
        // @can in the sidebar, and phase E's user controller.
        // Laravel 11+ dropped AuthServiceProvider, so Gates live here now.
        Gate::define('manage-users', fn (User $user) => $user->isManager());
    }
}
