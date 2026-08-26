<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Fuente única de permisos. Los controladores y las vistas Blade usan
 * @can('move-classes') en vez de checar roles a mano, así los permisos
 * viven en un solo lugar.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Ver horario: todos los roles autenticados.
        Gate::define('view-schedule', fn(User $u) => (bool) $u->active);

        // Mover/agendar clases: recepción, coordinación, admin.
        Gate::define('move-classes', fn(User $u) => $u->hasAnyRole(Role::canMoveClasses()));

        // Asistencia de alumnos: instructor (y admin para soporte).
        Gate::define('mark-member-attendance', fn(User $u) =>
        $u->hasAnyRole([Role::Instructor, Role::Admin]));

        // Asistencia de instructores + sustituciones: coordinador (y admin).
        Gate::define('mark-instructor-attendance', fn(User $u) =>
        $u->hasAnyRole([Role::Coordinator, Role::Admin]));

        // Reportes de pago e importación de socios: solo admin.
        Gate::define('view-reports', fn(User $u) => $u->isRole(Role::Admin));
        Gate::define('import-members', fn(User $u) => $u->isRole(Role::Admin));

        // Gestión de socios: admin, coordinador, recepción.
        Gate::define('manage-members', fn(User $u) =>
        $u->hasAnyRole([Role::Admin, Role::Coordinator, Role::Receptionist]));
    }
}
