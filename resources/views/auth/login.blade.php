<!DOCTYPE html>
<html lang="es" data-theme="{{ request()->cookie('sf-theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar — Swim Fitness</title>

    {{-- Guardrail anti-FOUC de iconos --}}
    <style>[class*="fa-"]{max-width:1.25em;max-height:1.25em;overflow:hidden}</style>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/theme.css'])
</head>
<body>
    <div class="login-split">
        {{-- Panel de marca (se oculta en móvil) --}}
        <div class="login-brand">
            <div class="brand-mark"><i class="fa-solid fa-water"></i></div>
            <h1 class="mb-2">Swim Fitness</h1>
            <p class="tagline">más que un deporte, un seguro de vida…</p>

            <div class="mt-5 d-flex flex-column gap-2 small" style="opacity:.85">
                <span><i class="fa-regular fa-calendar-days me-2"></i> Horarios y clases</span>
                <span><i class="fa-regular fa-id-card me-2"></i> Control de socios</span>
                <span><i class="fa-solid fa-user-check me-2"></i> Asistencia y pagos</span>
            </div>
        </div>

        {{-- Formulario --}}
        <div class="login-form-side">
            <div class="login-form-card">
                {{-- Marca compacta visible solo en móvil (cuando el panel se oculta) --}}
                <div class="text-center mb-4 d-md-none">
                    <i class="fa-solid fa-water fs-2" style="color:var(--brand-teal)"></i>
                    <h1 class="h4 mt-2 mb-0">Swim Fitness</h1>
                </div>

                <div class="mb-4">
                    <h2 class="h4 mb-1">Bienvenido</h2>
                    <p class="text-muted small mb-0">Ingresa a tu panel de gestión.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small">Correo</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email') }}" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" id="pw" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Recordarme</label>
                    </div>
                    <button class="btn btn-brand w-100">
                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Ingresar
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
