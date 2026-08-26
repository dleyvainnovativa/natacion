<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Paginación con markup de Bootstrap 5 (arregla el paginador roto).
        Paginator::useBootstrapFive();

        // Gates de T5/T6 (sin cambios).
        Gate::define('record-payments', fn(User $u) =>
        $u->hasAnyRole([Role::Admin, Role::Receptionist]));

        Gate::define('manage-maintenance', fn(User $u) =>
        $u->hasAnyRole([Role::Admin, Role::Coordinator]));
    }
}
