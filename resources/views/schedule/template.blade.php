@extends('layouts.app')
@section('title', 'Plantilla de horario — Swim Fitness')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="{{ route('schedule.index') }}" class="text-decoration-none small text-muted">
                <i class="fa-solid fa-arrow-left me-1"></i> Horario
            </a>
            <h1 class="h3 mt-2 mb-0">Plantilla recurrente</h1>
            <p class="text-muted mb-0">Las clases aquí se repiten cada semana y generan las sesiones.</p>
        </div>
        <button class="btn btn-brand" onclick="SF.modal.show('slotModal')">
            <i class="fa-solid fa-plus me-1"></i> Agregar clase
        </button>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif
    @if (session('warnings') && count(session('warnings')))
        <div class="alert alert-warning py-2">
            <strong>Avisos de conflicto:</strong>
            <ul class="mb-0 small">
                @foreach (session('warnings') as $w) <li>{{ $w }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="schedule-grid">
        @foreach ($weekdays as $iso => $label)
            <div class="schedule-col">
                <div class="schedule-col-head"><span class="fw-600">{{ $label }}</span></div>
                <div class="schedule-col-body">
                    @forelse ($slots->get($iso, collect()) as $slot)
                        <div class="session-card chip-{{ $slot->program?->color ?? 'teal' }}">
                            <div class="d-flex justify-content-between">
                                <span class="mono small fw-600">{{ \Illuminate\Support\Str::of($slot->start_time)->substr(0,5) }}</span>
                                <span class="small text-muted">{{ $slot->duration_min }}′</span>
                            </div>
                            <div class="fw-600 small">{{ $slot->program?->name }}</div>
                            <div class="small text-muted">{{ $slot->lane?->label ?? 'Sin carril' }}</div>
                            <div class="small">{{ $slot->instructor?->name ?? 'Sin instructor' }}</div>
                            <div class="small text-muted mt-1">
                                <i class="fa-solid fa-users"></i> {{ $slot->members->count() }} en roster
                            </div>
                            <div class="d-flex gap-1 mt-2">
                                <a href="{{ route('schedule.slots.roster', $slot) }}"
                                   class="btn btn-sm btn-outline-secondary flex-fill">Roster</a>
                                <form method="POST" action="{{ route('schedule.slots.destroy', $slot) }}"
                                      onsubmit="return confirm('¿Quitar esta clase de la plantilla?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small text-center py-3">—</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal agregar clase a la plantilla --}}
    <div class="modal fade" id="slotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('schedule.slots.store') }}" class="modal-content app-card">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Agregar clase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Programa *</label>
                        <select name="program_id" class="form-select" required>
                            @foreach ($programs as $p)
                                <option value="{{ $p->id }}" data-duration="{{ $p->duration_min }}">
                                    {{ $p->name }} ({{ $p->duration_min }}′, cupo {{ $p->lane_capacity }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Día *</label>
                            <select name="weekday" class="form-select" required>
                                @foreach ($weekdays as $iso => $label)
                                    <option value="{{ $iso }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Hora *</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="form-label small">Carril</label>
                            <select name="lane_id" class="form-select">
                                <option value="">— Sin asignar —</option>
                                @foreach ($lanes as $lane)
                                    <option value="{{ $lane->id }}">{{ $lane->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Instructor</label>
                            <select name="instructor_id" class="form-select">
                                <option value="">— Sin asignar —</option>
                                @foreach ($instructors as $i)
                                    <option value="{{ $i->id }}">{{ $i->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Duración (min) — vacío = la del programa</label>
                        <input type="number" name="duration_min" class="form-control" min="5" max="240">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand">Agregar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
