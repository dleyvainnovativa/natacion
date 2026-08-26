<!DOCTYPE html>
{{-- Lee la cookie de tema en el primer render para evitar parpadeo. --}}
<html lang="es" data-theme="{{ request()->cookie('sf-theme', 'light') }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Swim Fitness')</title>

    {{-- Guardrail anti-FOUC: limita el tamaño de los iconos ANTES de que
         cargue la webfont, para que no aparezca un icono gigante. --}}
    <style>
        .fa, .fa-solid, .fa-regular, .fa-brands, [class*="fa-"] {
            max-width: 1.25em; max-height: 1.25em; overflow: hidden;
        }
        body { visibility: visible; }
    </style>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/theme.css', 'resources/js/app.js'])
</head>

<body>
    {{-- ===== Topbar fija (en todos los tamaños) ===== --}}
    <header class="app-topbar">
        {{-- Hamburguesa: solo móvil (abre el offcanvas) --}}
        <button class="app-hamburger" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#navOffcanvas"
                aria-controls="navOffcanvas" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>

        <a href="{{ route('schedule.index') }}" class="app-topbar-brand">
            <i class="fa-solid fa-water" style="color: var(--brand-teal)"></i>
            <span>Swim Fitness</span>
        </a>

        <div class="app-topbar-spacer"></div>

        {{-- Perfil + tema + logout (movido aquí desde el sidebar) --}}
        <div class="app-topbar-user">
            <span class="user-name">{{ auth()->user()->name }}</span>
            <span class="badge-role user-role">{{ auth()->user()->role->label() }}</span>

            <button class="btn btn-sm btn-outline-secondary" data-theme-toggle title="Cambiar tema">
                <i class="fa-solid fa-circle-half-stroke"></i>
            </button>

            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-outline-secondary" title="Salir">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span class="d-none d-lg-inline">Salir</span>
                </button>
            </form>
        </div>
    </header>

    {{-- ===== Cuerpo: sidebar (desktop) + main ===== --}}
    <div class="app-shell">
        <aside class="app-sidebar">
            @include('partials.nav-links')
        </aside>

        <main class="app-main">
            @yield('content')
        </main>
    </div>

    {{-- ===== Offcanvas (móvil): mismos enlaces ===== --}}
    <div class="offcanvas offcanvas-start app-offcanvas" tabindex="-1" id="navOffcanvas"
         aria-labelledby="navOffcanvasLabel">
        <div class="offcanvas-header">
            <span class="d-flex align-items-center gap-2 fw-bold" id="navOffcanvasLabel">
                <i class="fa-solid fa-water" style="color: var(--brand-teal)"></i> Swim Fitness
            </span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            @include('partials.nav-links')
        </div>
    </div>

    <div id="toast-root" aria-live="polite" aria-atomic="true"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
