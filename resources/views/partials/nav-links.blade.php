{{--
    Enlaces de navegación — definidos UNA sola vez y reutilizados en el sidebar
    (desktop) y en el offcanvas (móvil). Así no se duplican ni se desincronizan.
--}}
<nav class="d-flex flex-column gap-1">
    {{-- Todos ven el horario --}}
    <a href="{{ route('schedule.index') }}"
       class="app-nav-link {{ request()->routeIs('schedule.*') ? 'active' : '' }}">
        <i class="fa-regular fa-calendar-days"></i> Horario
    </a>

    @can('manage-members')
    <a href="{{ route('members.index') }}"
       class="app-nav-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
        <i class="fa-regular fa-id-card"></i> Socios
    </a>
    @endcan

    @can('mark-member-attendance')
    <a href="{{ route('attendance.members.index') }}"
       class="app-nav-link {{ request()->routeIs('attendance.members.*') ? 'active' : '' }}">
        <i class="fa-solid fa-user-check"></i> Asistencia alumnos
    </a>
    @endcan

    @can('mark-instructor-attendance')
    <a href="{{ route('attendance.instructors.index') }}"
       class="app-nav-link {{ request()->routeIs('attendance.instructors.*') ? 'active' : '' }}">
        <i class="fa-solid fa-clipboard-user"></i> Asistencia instructores
    </a>
    @endcan

    @can('view-reports')
    <a href="{{ route('reports.index') }}"
       class="app-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
        <i class="fa-solid fa-chart-line"></i> Reportes
    </a>
    @endcan

    @can('record-payments')
    <a href="{{ route('payments.index') }}"
       class="app-nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
        <i class="fa-solid fa-money-bill-wave"></i> Cobros
    </a>
    @endcan

    @can('manage-maintenance')
    <a href="{{ route('maintenance.index') }}"
       class="app-nav-link {{ request()->routeIs('maintenance.*') ? 'active' : '' }}">
        <i class="fa-solid fa-wrench"></i> Mantenimiento
    </a>
    @endcan
</nav>
