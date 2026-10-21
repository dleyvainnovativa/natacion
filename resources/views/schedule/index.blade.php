@extends('layouts.app')
@section('title', 'Horario — Swim Fitness')

@section('content')

    {{-- Encabezado de página (patrón nuevo, con eyebrow + acento) --}}
    <div class="page-head">
        <div>
            <div class="page-eyebrow"><i class="fa-regular fa-calendar-days"></i> Programación</div>
            <h1 class="page-title">Horario semanal</h1>
            <p class="page-sub">Semana del {{ $weekStart->isoFormat('D [de] MMMM, YYYY') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            {{-- Cambiar entre vista día / semana --}}
            <div class="btn-group btn-group-sm">
                <a href="{{ route('schedule.day', $filters) }}" class="btn btn-outline-secondary">Día</a>
                <a href="{{ route('schedule.index', $filters) }}" class="btn btn-brand">Semana</a>
            </div>

            {{-- Patrón de días: L-M-V / M-J / toda la semana (petición del cliente) --}}
            @php $patBase = array_merge($filters, ['week' => request('week')]); @endphp
            <div class="btn-group btn-group-sm">
                <a href="{{ route('schedule.index', array_merge($patBase, ['pattern' => 'lmv'])) }}"
                   class="btn {{ $pattern === 'lmv' ? 'btn-brand' : 'btn-outline-secondary' }}">L·M·V</a>
                <a href="{{ route('schedule.index', array_merge($patBase, ['pattern' => 'mj'])) }}"
                   class="btn {{ $pattern === 'mj' ? 'btn-brand' : 'btn-outline-secondary' }}">M·J</a>
                <a href="{{ route('schedule.index', array_merge($patBase, ['pattern' => 'semana'])) }}"
                   class="btn {{ $pattern === 'semana' ? 'btn-brand' : 'btn-outline-secondary' }}">Semana</a>
            </div>

            <div class="btn-group">
                <a href="{{ route('schedule.index', array_merge($filters, ['pattern' => $pattern, 'week' => $prevWeek])) }}"
                   class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
                <a href="{{ route('schedule.index', array_merge($filters, ['pattern' => $pattern])) }}"
                   class="btn btn-outline-secondary btn-sm">Hoy</a>
                <a href="{{ route('schedule.index', array_merge($filters, ['pattern' => $pattern, 'week' => $nextWeek])) }}"
                   class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
            @can('move-classes')
                <a href="{{ route('schedule.template') }}" class="btn btn-brand btn-sm">
                    <i class="fa-solid fa-table-cells me-1"></i> Editar plantilla
                </a>
            @endcan
        </div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    {{-- Barra de filtros --}}
    <form method="GET" class="filter-bar app-card mb-3">
        <input type="hidden" name="week" value="{{ request('week') }}">
        <input type="hidden" name="pattern" value="{{ $pattern }}">
        <div class="filter-field">
            <label>Instructor</label>
            <select name="instructor" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach ($instructors as $i)
                    <option value="{{ $i->id }}" @selected(($filters['instructor'] ?? null) == $i->id)>{{ $i->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label>Programa</label>
            <select name="program" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach ($programs as $p)
                    <option value="{{ $p->id }}" @selected(($filters['program'] ?? null) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label>Carril</label>
            <select name="lane" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach ($allLanes as $l)
                    <option value="{{ $l->id }}" @selected(($filters['lane'] ?? null) == $l->id)>{{ $l->label }}</option>
                @endforeach
            </select>
        </div>
        @if (array_filter($filters))
            <a href="{{ route('schedule.index', ['week' => request('week'), 'pattern' => $pattern]) }}"
               class="btn btn-sm btn-outline-secondary align-self-end">Limpiar</a>
        @endif
    </form>

    @php
        // Escala idéntica a la vista de día. El JS lee --px-per-min del lienzo,
        // así que basta cambiarlo aquí. Subido a 1.6 para que quepan instructor
        // + conteo en cada bloque sin tener que abrir nada.
        $pxPerMin = 1.6;
        $canvasH  = ($endMin - $startMin) * $pxPerMin;
        // Carriles a pintar por día: los reales + (si aplica) "sin carril" (id 0).
        $renderLanes = $lanes->map(fn ($l) => ['id' => $l->id, 'label' => $l->label])->all();
        if ($hasUnassigned) {
            $renderLanes[] = ['id' => 0, 'label' => 'Sin carril'];
        }
    @endphp

    <div class="cal-layout">
    <div class="cal-main">
    @unless ($hasSessions)
        <div class="app-card p-5 text-center text-muted">
            <i class="fa-regular fa-calendar-xmark fs-1 mb-3 d-block" style="color:var(--brand-teal)"></i>
            No hay clases esta semana con estos filtros.
        </div>
    @else
        @php $L = count($renderLanes); @endphp
        {{-- LIENZO SEMANAL (rejilla reconstruida): un solo contenedor con scroll,
             cabecera pegajosa arriba, gutter de horas pegajoso a la izquierda,
             columnas flexibles (llenan el ancho; sólo hacen scroll si no caben). --}}
        <div class="cal week-canvas app-card"
             style="--px-per-min: {{ $pxPerMin }}; --canvas-h: {{ $canvasH }}px; --cols: {{ count($weekdays) * $L }};"
             data-start-min="{{ $startMin }}"
             data-end-min="{{ $endMin }}">

            {{-- Cabecera: esquina + día (abarca sus carriles) + carriles --}}
            <div class="cal-head">
                <div class="cal-corner"></div>
                @foreach ($weekdays as $iso => $label)
                    @php $dayDate = $weekStart->copy()->addDays($iso - 1); $startCol = 2 + $loop->index * $L; @endphp
                    <a class="cal-dayhead {{ $dayDate->isToday() ? 'is-today' : '' }}"
                       style="grid-column: {{ $startCol }} / span {{ $L }};"
                       href="{{ route('schedule.day', array_merge($filters, ['date' => $dayDate->toDateString()])) }}"
                       title="Ver el día en detalle">
                        <span class="cd-day">{{ $label }}</span>
                        <span class="cd-date">{{ $dayDate->format('d/m') }}</span>
                    </a>
                    @foreach ($renderLanes as $j => $rl)
                        <div class="cal-lanehead {{ $j === $L - 1 ? 'cal-daysep' : '' }}"
                             style="grid-column: {{ $startCol + $j }};">{{ $rl['label'] }}</div>
                    @endforeach
                @endforeach
            </div>

            {{-- Cuerpo: gutter de horas + una columna por (día × carril) --}}
            <div class="cal-body">
                <div class="cal-gutter" style="height: var(--canvas-h)">
                    @for ($m = $startMin; $m <= $endMin; $m += 60)
                        <div class="dc-hour-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px">
                            <span class="dc-hour-label">{{ sprintf('%02d:%02d', intdiv($m,60), $m%60) }}</span>
                        </div>
                    @endfor
                </div>

                @foreach ($weekdays as $iso => $label)
                    @php $dayDate = $weekStart->copy()->addDays($iso - 1); $startCol = 2 + $loop->index * $L; @endphp
                    @foreach ($renderLanes as $j => $rl)
                        <div class="cal-col dc-lane-body {{ $j === $L - 1 ? 'cal-daysep' : '' }}"
                             style="grid-column: {{ $startCol + $j }}; height: var(--canvas-h)"
                             data-lane-id="{{ $rl['id'] ?: '' }}"
                             data-date="{{ $dayDate->toDateString() }}">
                            @for ($m = $startMin; $m <= $endMin; $m += 60)
                                <div class="dc-grid-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px"></div>
                            @endfor
                            @foreach (($byDay[$iso][$rl['id']] ?? []) as $s)
                                @include('schedule.partials.cal-event', ['s' => $s, 'pxPerMin' => $pxPerMin, 'startMin' => $startMin])
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        <p class="text-muted small mt-2">
            Arrastra una clase a otro día, carril u hora (se ajusta cada 10 min).
            La duración la fija el programa. Toca el encabezado de un día para verlo en detalle.
        </p>
    @endunless
    </div>{{-- /.cal-main --}}
    @can('move-classes')
        @include('schedule.partials.member-panel')
    @endcan
    </div>{{-- /.cal-layout --}}

    @include('schedule.partials.sessions-payload')

    @can('move-classes')
        @include('schedule.partials.move-modal')
        @include('schedule.partials.move-member-modal')
    @endcan
@endsection
