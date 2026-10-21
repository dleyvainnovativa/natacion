@extends('layouts.app')
@section('title', 'Horario del día — Swim Fitness')

@php
    // Escala: 1 minuto = 1.6px. El JS lee --px-per-min del lienzo (fallback 1.2),
    // así que basta ajustarlo aquí. Subido para que el bloque muestre hora +
    // clase + instructor + conteo sin abrir nada.
    $pxPerMin = 1.6;
    $totalMin = $endMin - $startMin;
    $canvasH  = $totalMin * $pxPerMin;
@endphp

@section('content')

    <div class="page-head">
        <div>
            <div class="page-eyebrow"><i class="fa-regular fa-calendar-days"></i> Programación</div>
            <h1 class="page-title">Horario del día</h1>
            <p class="page-sub">{{ $date->isoFormat('dddd D [de] MMMM, YYYY') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            {{-- Cambiar entre vista día / semana --}}
            <div class="btn-group btn-group-sm">
                <a href="{{ route('schedule.day', $filters) }}" class="btn btn-brand">Día</a>
                <a href="{{ route('schedule.index', $filters) }}" class="btn btn-outline-secondary">Semana</a>
            </div>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('schedule.day', array_merge($filters, ['date' => $prevDay])) }}"
                   class="btn btn-outline-secondary"><i class="fa-solid fa-chevron-left"></i></a>
                <a href="{{ route('schedule.day', $filters) }}" class="btn btn-outline-secondary">Hoy</a>
                <a href="{{ route('schedule.day', array_merge($filters, ['date' => $nextDay])) }}"
                   class="btn btn-outline-secondary"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
            {{-- Selector de fecha: ir a cualquier día --}}
            <form method="GET" class="d-flex align-items-center gap-1" id="dayDateForm">
                @foreach ($filters as $fk => $fv)
                    <input type="hidden" name="{{ $fk }}" value="{{ $fv }}">
                @endforeach
                <input type="date" name="date" value="{{ $date->toDateString() }}"
                       class="form-control form-control-sm" style="width:auto"
                       onchange="document.getElementById('dayDateForm').submit()"
                       title="Ir a una fecha">
            </form>
            {{-- Editar la plantilla del mes de este día --}}
            @can('move-classes')
                <a href="{{ route('schedule.template', ['month' => $date->format('Y-m')]) }}"
                   class="btn btn-brand btn-sm">
                    <i class="fa-solid fa-table-cells me-1"></i> Editar plantilla
                </a>
            @endcan
        </div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    {{-- Filtros (instructor / programa) --}}
    <form method="GET" class="filter-bar app-card mb-3">
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
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
        @if (array_filter($filters))
            <a href="{{ route('schedule.day', ['date' => $date->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary align-self-end">Limpiar</a>
        @endif
    </form>

    <div class="cal-layout">
    <div class="cal-main">
    {{-- LIENZO DEL DÍA (misma rejilla que la semana: cabecera + gutter pegajosos,
         columnas de carril flexibles a lo ancho). --}}
    @php
        $dayLanes = $lanes->map(fn ($l) => ['id' => $l->id, 'label' => $l->label])->all();
        if (! empty($byLane[0])) { $dayLanes[] = ['id' => 0, 'label' => 'Sin carril']; }
    @endphp
    <div class="cal cal--day day-canvas app-card"
         style="--px-per-min: {{ $pxPerMin }}; --canvas-h: {{ $canvasH }}px; --cols: {{ count($dayLanes) }};"
         data-start-min="{{ $startMin }}"
         data-end-min="{{ $endMin }}"
         data-date="{{ $date->toDateString() }}">

        {{-- Cabecera: esquina + carriles (una sola fila) --}}
        <div class="cal-head">
            <div class="cal-corner" style="grid-row: 1"></div>
            @foreach ($dayLanes as $j => $rl)
                <div class="cal-lanehead {{ $rl['id'] === 0 ? 'text-muted' : '' }}"
                     style="grid-column: {{ 2 + $j }}; grid-row: 1;">{{ $rl['label'] }}</div>
            @endforeach
        </div>

        {{-- Cuerpo: gutter + una columna por carril --}}
        <div class="cal-body">
            <div class="cal-gutter" style="height: var(--canvas-h)">
                @for ($m = $startMin; $m <= $endMin; $m += 60)
                    <div class="dc-hour-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px">
                        <span class="dc-hour-label">{{ sprintf('%02d:%02d', intdiv($m,60), $m%60) }}</span>
                    </div>
                @endfor
            </div>

            @foreach ($dayLanes as $j => $rl)
                <div class="cal-col dc-lane-body"
                     style="grid-column: {{ 2 + $j }}; height: var(--canvas-h)"
                     data-lane-id="{{ $rl['id'] ?: '' }}"
                     data-date="{{ $date->toDateString() }}">
                    @for ($m = $startMin; $m <= $endMin; $m += 60)
                        <div class="dc-grid-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px"></div>
                    @endfor
                    @foreach (($byLane[$rl['id']] ?? []) as $s)
                        @include('schedule.partials.cal-event', ['s' => $s, 'pxPerMin' => $pxPerMin, 'startMin' => $startMin])
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <p class="text-muted small mt-2">
        Arrastra una clase a otro carril u hora (se ajusta cada 10 min). La duración
        la fija el programa. Si el carril queda ocupado a esa hora, verás un aviso pero se permite.
    </p>
    </div>{{-- /.cal-main --}}
    @can('move-classes')
        @include('schedule.partials.member-panel')
    @endcan
    </div>{{-- /.cal-layout --}}

    @include('schedule.partials.sessions-payload')


    @can('move-classes')
        <div id="dc-toast-anchor"></div>
        @include('schedule.partials.move-modal')
        @include('schedule.partials.move-member-modal')
        @include('schedule.partials.quick-add-modal', [
            'programs' => $programs, 'instructors' => $instructors,
            'lanes' => $lanes, 'members' => $panelMembers,
        ])
    @endcan
@endsection
