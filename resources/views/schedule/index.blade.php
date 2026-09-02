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
            <div class="btn-group">
                <a href="{{ route('schedule.index', array_merge($filters, ['week' => $prevWeek])) }}"
                   class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
                <a href="{{ route('schedule.index', $filters) }}" class="btn btn-outline-secondary btn-sm">Hoy</a>
                <a href="{{ route('schedule.index', array_merge($filters, ['week' => $nextWeek])) }}"
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
            <a href="{{ route('schedule.index', ['week' => request('week')]) }}"
               class="btn btn-sm btn-outline-secondary align-self-end">Limpiar</a>
        @endif
    </form>

    @php
        // Escala idéntica a la vista de día. Debe COINCIDIR con --px-per-min
        // del CSS y PX_PER_MIN del JS.
        $pxPerMin = 1.2;
        $canvasH  = ($endMin - $startMin) * $pxPerMin;
        // Carriles a pintar por día: los reales + (si aplica) "sin carril" (id 0).
        $renderLanes = $lanes->map(fn ($l) => ['id' => $l->id, 'label' => $l->label])->all();
        if ($hasUnassigned) {
            $renderLanes[] = ['id' => 0, 'label' => 'Sin carril'];
        }
    @endphp

    @unless ($hasSessions)
        <div class="app-card p-5 text-center text-muted">
            <i class="fa-regular fa-calendar-xmark fs-1 mb-3 d-block" style="color:var(--brand-teal)"></i>
            No hay clases esta semana con estos filtros.
        </div>
    @else
        {{-- LIENZO SEMANAL: gutter de horas + 7 grupos de día, cada uno con sus
             carriles. Desplazable en horizontal (21 columnas caben con scroll). --}}
        <div class="week-canvas app-card"
             style="--px-per-min: {{ $pxPerMin }}; --canvas-h: {{ $canvasH }}px; --start-min: {{ $startMin }};"
             data-start-min="{{ $startMin }}"
             data-end-min="{{ $endMin }}">

            {{-- Gutter de horas (izquierda, pegajoso) --}}
            <div class="wc-gutter">
                <div class="wc-day-head">&nbsp;</div>
                <div class="wc-lane-head">&nbsp;</div>
                <div class="wc-hours" style="height: var(--canvas-h)">
                    @for ($m = $startMin; $m <= $endMin; $m += 60)
                        <div class="dc-hour-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px">
                            <span class="dc-hour-label">{{ sprintf('%02d:%02d', intdiv($m,60), $m%60) }}</span>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Un grupo por día --}}
            @foreach ($weekdays as $iso => $label)
                @php $dayDate = $weekStart->copy()->addDays($iso - 1); @endphp
                <div class="wc-day @if($dayDate->isToday()) wc-day-today @endif">
                    <div class="wc-day-head">
                        <a class="tg-day-link"
                           href="{{ route('schedule.day', array_merge($filters, ['date' => $dayDate->toDateString()])) }}"
                           title="Ver el día en detalle">
                            <span class="tg-day">{{ $label }}</span>
                            <span class="tg-date">{{ $dayDate->format('d/m') }}</span>
                        </a>
                    </div>

                    <div class="wc-lanes">
                        @foreach ($renderLanes as $rl)
                            <div class="wc-lane">
                                <div class="wc-lane-head @if($rl['id']===0) text-muted @endif">{{ $rl['label'] }}</div>
                                <div class="dc-lane-body wc-lane-body" style="height: var(--canvas-h)"
                                     data-lane-id="{{ $rl['id'] ?: '' }}"
                                     data-date="{{ $dayDate->toDateString() }}">
                                    {{-- Líneas de hora de fondo --}}
                                    @for ($m = $startMin; $m <= $endMin; $m += 60)
                                        <div class="dc-grid-line" style="top: {{ ($m - $startMin) * $pxPerMin }}px"></div>
                                    @endfor

                                    {{-- Clases de este día/carril --}}
                                    @foreach (($byDay[$iso][$rl['id']] ?? []) as $s)
                                        @php
                                            $mins = $s->starts_at->hour * 60 + $s->starts_at->minute;
                                            $top  = ($mins - $startMin) * $pxPerMin;
                                            $h    = max(22, $s->duration_min * $pxPerMin);
                                            $color = $s->program?->color ?? 'teal';
                                            $subbed = $s->actual_instructor_id
                                                   && $s->actual_instructor_id !== $s->scheduled_instructor_id;
                                        @endphp
                                        <div class="dc-event wc-event chip-{{ $color }}"
                                             style="top: {{ $top }}px; height: {{ $h }}px"
                                             data-session-id="{{ $s->id }}"
                                             data-duration="{{ $s->duration_min }}"
                                             data-starts="{{ $s->starts_at->format('Y-m-d\TH:i') }}"
                                             data-lane="{{ $s->lane_id }}"
                                             data-instructor="{{ $s->scheduled_instructor_id }}"
                                             title="{{ $s->starts_at->format('H:i') }} · {{ $s->program?->name }} · {{ $s->actualInstructor?->name ?? '—' }} · {{ $s->members->count() }} alumnos">
                                            <div class="dc-event-time mono">{{ $s->starts_at->format('H:i') }}</div>
                                            <div class="dc-event-title">{{ $s->program?->name ?? 'Clase' }}</div>
                                            <div class="wc-event-hover">
                                                {{ $s->actualInstructor?->name ?? '—' }}
                                                @if ($subbed)<span class="badge-role">supl.</span>@endif
                                                · <i class="fa-solid fa-users"></i> {{ $s->members->count() }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-2">
            Arrastra una clase a otro día, carril u hora (se ajusta cada 10 min).
            La duración la fija el programa. Si el carril queda ocupado, verás un
            aviso pero se permite. Toca el encabezado de un día para verlo en detalle.
        </p>
    @endunless

    @can('move-classes')
        @include('schedule.partials.move-modal')
        @include('schedule.partials.move-member-modal')
    @endcan
@endsection
