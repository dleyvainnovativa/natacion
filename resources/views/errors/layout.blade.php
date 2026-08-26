{{--
    Layout base para páginas de error. AUTÓNOMO A PROPÓSITO: no usa layouts/app,
    no usa @vite y no consulta la BD. Un error (sobre todo 500/503) puede ocurrir
    cuando los assets no están compilados, la BD está caída o no hay sesión; la
    página de error nunca debe depender de nada de eso. Por eso el CSS crítico va
    embebido con los tokens de marca (mismos valores que theme.css).

    Las vistas hijas definen: @section('code'|'icon'|'heading'|'message').
--}}
<!DOCTYPE html>
<html lang="es" data-theme="{{ request()->cookie('sf-theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') — Swim Fitness</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        /* Tokens de marca (espejo de theme.css) — embebidos para independencia. */
        :root {
            --brand-teal: #49a5b2; --brand-indigo: #6378af;
            --bg: #f6f8fa; --surface: #ffffff; --border: #e2e8ee;
            --text: #1c2430; --text-muted: #5c6875;
            --radius: 8px; --shadow: 0 2px 8px rgba(28,36,48,.08);
        }
        [data-theme="dark"] {
            --bg: #0f151c; --surface: #171f29; --border: #2a3744;
            --text: #e7edf3; --text-muted: #9aa8b6;
            --shadow: 0 2px 8px rgba(0,0,0,.35);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg); color: var(--text);
            -webkit-font-smoothing: antialiased; padding: 1rem;
        }
        .err-card {
            width: 100%; max-width: 440px; text-align: center;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow);
            padding: 3rem 2rem;
        }
        .err-icon { font-size: 2.5rem; color: var(--brand-teal); }
        .err-code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-weight: 600; font-size: 3rem; line-height: 1;
            color: var(--brand-indigo); margin-top: 1rem;
        }
        .err-heading { font-size: 1.15rem; font-weight: 600; margin: 1rem 0 .5rem; }
        .err-msg { color: var(--text-muted); font-size: .9rem; margin-bottom: 1.5rem; }
        .err-actions { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }
        .err-btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem 1rem; border-radius: var(--radius);
            text-decoration: none; font-size: .9rem; border: 1px solid transparent;
            cursor: pointer;
        }
        .err-btn-primary { background: var(--brand-teal); color: #fff; }
        .err-btn-primary:hover { background: #3d8b96; }
        .err-btn-ghost { border-color: var(--border); color: var(--text-muted); background: transparent; }
        .err-btn-ghost:hover { background: var(--bg); }
        .err-foot {
            margin-top: 1.75rem; padding-top: 1rem;
            border-top: 1px solid var(--border);
            color: var(--text-muted); font-size: .8rem;
            display: inline-flex; align-items: center; gap: .4rem;
        }
        :focus-visible { outline: 2px solid var(--brand-teal); outline-offset: 2px; }
    </style>
</head>
<body>
    <div class="err-card">
        <i class="fa-solid @yield('icon') err-icon"></i>
        <div class="err-code">@yield('code')</div>
        <h1 class="err-heading">@yield('heading')</h1>
        <p class="err-msg">@yield('message')</p>

        <div class="err-actions">
            <a href="{{ url('/') }}" class="err-btn err-btn-primary">
                <i class="fa-solid fa-house"></i> Ir al inicio
            </a>
            <a href="javascript:history.back()" class="err-btn err-btn-ghost">Regresar</a>
        </div>

        <div style="display:flex; justify-content:center">
            <span class="err-foot">
                <i class="fa-solid fa-water" style="color:var(--brand-teal)"></i> Swim Fitness
            </span>
        </div>
    </div>
</body>
</html>
