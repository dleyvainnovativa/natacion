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
                @foreach ($lanes as $l)
                    <option value="{{ $l->id }}" @selected(($filters['lane'] ?? null) == $l->id)>{{ $l->label }}</option>
                @endforeach
            </select>
        </div>
        @if (array_filter($filters))
            <a href="{{ route('schedule.index', ['week' => request('week')]) }}"
               class="btn btn-sm btn-outline-secondary align-self-end">Limpiar</a>
        @endif
    </form>

    @if (empty($times))
        <div class="app-card p-5 text-center text-muted">
            <i class="fa-regular fa-calendar-xmark fs-1 mb-3 d-block" style="color:var(--brand-teal)"></i>
            No hay clases esta semana con estos filtros.
        </div>
    @else
        {{-- Rejilla HORAS × DÍAS --}}
        <div class="app-card p-0" style="overflow:auto">
            <table class="time-grid">
                <thead>
                    <tr>
                        <th class="tg-time-col">Hora</th>
                        @foreach ($weekdays as $iso => $label)
                            @php $dayDate = $weekStart->copy()->addDays($iso - 1); @endphp
                            <th>
                                <div class="tg-day">{{ $label }}</div>
                                <div class="tg-date">{{ $dayDate->format('d/m') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($times as $time)
                        <tr>
                            <td class="tg-time-col mono">{{ $time }}</td>
                            @foreach ($weekdays as $iso => $label)
                                @php $dayDate = $weekStart->copy()->addDays($iso - 1); @endphp
                                <td class="tg-cell schedule-col" data-date="{{ $dayDate->format('Y-m-d') }}">
                                    @foreach (($grid[$time][$iso] ?? []) as $session)
                                        @include('schedule.partials.session-card', ['session' => $session])
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @can('move-classes')
        @include('schedule.partials.move-modal')
        @include('schedule.partials.move-member-modal')
    @endcan
@endsection
