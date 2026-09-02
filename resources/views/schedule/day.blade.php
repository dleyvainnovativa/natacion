@extends('layouts.app')
@section('title', 'Horario del día — Swim Fitness')

@php
    // Escala: 1 minuto = 1.2px. Debe COINCIDIR con --px-per-min en el CSS y con
    // PX_PER_MIN en el JS. Se expone como variable CSS para que todo cuadre.
    $pxPerMin = 1.2;
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

    {{-- LIENZO DEL DÍA --}}
    <div class="day-canvas app-card"
         style="--px-per-min: {{ $pxPerMin }}; --canvas-h: {{ $canvasH }}px; --start-min: {{ $startMin }};"
         data-start-min="{{ $startMin }}"
         data-end-min="{{ $endMin }}"
         data-date="{{ $date->toDateString() }}">

        {{-- Columna de horas (gutter) --}}
        <div class="dc-gutter">
            <div class="dc-lane-head">&nbsp;</div>
            <div class="dc-hours" style="height: var(--canvas-h)">
                @for ($m = $startMin; $m <= $endMin; $m += 60)
                    <div class="dc-hour-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px">
                        <span class="dc-hour-label">{{ sprintf('%02d:%02d', intdiv($m,60), $m%60) }}</span>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Una columna por carril --}}
        <div class="dc-lanes">
            @foreach ($lanes as $lane)
                <div class="dc-lane" data-lane-id="{{ $lane->id }}">
                    <div class="dc-lane-head">{{ $lane->label }}</div>
                    <div class="dc-lane-body" style="height: var(--canvas-h)"
                         data-lane-id="{{ $lane->id }}"
                         data-date="{{ $date->toDateString() }}">
                        {{-- Líneas de hora de fondo --}}
                        @for ($m = $startMin; $m <= $endMin; $m += 60)
                            <div class="dc-grid-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px"></div>
                        @endfor

                        {{-- Clases de este carril --}}
                        @foreach (($byLane[$lane->id] ?? []) as $s)
                            @php
                                $mins = $s->starts_at->hour * 60 + $s->starts_at->minute;
                                $top  = ($mins - $startMin) * $pxPerMin;
                                $h    = max(24, $s->duration_min * $pxPerMin); // mínimo visible
                                $color = $s->program?->color ?? 'teal';
                                $subbed = $s->actual_instructor_id
                                       && $s->actual_instructor_id !== $s->scheduled_instructor_id;
                            @endphp
                            <div class="dc-event chip-{{ $color }}"
                                 style="top: {{ $top }}px; height: {{ $h }}px"
                                 draggable="true"
                                 data-session-id="{{ $s->id }}"
                                 data-duration="{{ $s->duration_min }}"
                                 data-starts="{{ $s->starts_at->format('Y-m-d\TH:i') }}"
                                 data-lane="{{ $s->lane_id }}"
                                 data-instructor="{{ $s->scheduled_instructor_id }}">
                                <div class="dc-event-time mono">{{ $s->starts_at->format('H:i') }}</div>
                                <div class="dc-event-title">{{ $s->program?->name ?? 'Clase' }}</div>
                                <div class="dc-event-meta">
                                    {{ $s->actualInstructor?->name ?? '—' }}
                                    @if ($subbed)<span class="badge-role">supl.</span>@endif
                                    · <i class="fa-solid fa-users"></i> {{ $s->members->count() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Columna "sin carril" (solo si hay clases sin asignar) --}}
            @if (! empty($byLane[0]))
                <div class="dc-lane" data-lane-id="">
                    <div class="dc-lane-head text-muted">Sin carril</div>
                    <div class="dc-lane-body" style="height: var(--canvas-h)" data-lane-id="" data-date="{{ $date->toDateString() }}">
                        @for ($m = $startMin; $m <= $endMin; $m += 60)
                            <div class="dc-grid-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px"></div>
                        @endfor
                        @foreach ($byLane[0] as $s)
                            @php
                                $mins = $s->starts_at->hour * 60 + $s->starts_at->minute;
                                $top  = ($mins - $startMin) * $pxPerMin;
                                $h    = max(24, $s->duration_min * $pxPerMin);
                            @endphp
                            <div class="dc-event chip-teal"
                                 style="top: {{ $top }}px; height: {{ $h }}px"
                                 draggable="true"
                                 data-session-id="{{ $s->id }}"
                                 data-duration="{{ $s->duration_min }}"
                                 data-starts="{{ $s->starts_at->format('Y-m-d\TH:i') }}"
                                 data-lane=""
                                 data-instructor="{{ $s->scheduled_instructor_id }}">
                                <div class="dc-event-time mono">{{ $s->starts_at->format('H:i') }}</div>
                                <div class="dc-event-title">{{ $s->program?->name ?? 'Clase' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <p class="text-muted small mt-2">
        Arrastra una clase a otro carril u hora (se ajusta cada 10 min). La duración
        la fija el programa. Si el carril queda ocupado a esa hora, verás un aviso pero se permite.
    </p>

    @can('move-classes')
        <div id="dc-toast-anchor"></div>
    @endcan
@endsection
