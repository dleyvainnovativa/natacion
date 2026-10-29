@extends('layouts.app')
@section('title', 'Plantilla de horario — Swim Fitness')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="{{ route('schedule.index') }}" class="text-decoration-none small text-muted">
                <i class="fa-solid fa-arrow-left me-1"></i> Horario
            </a>
            <h1 class="h3 mt-2 mb-0">Plantilla · {{ $template->display_label }}</h1>
            <p class="text-muted mb-0">Las clases aquí se repiten cada semana de este mes y generan las sesiones.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            {{-- Selector de mes --}}
            <form method="GET" class="d-flex align-items-center gap-2" id="monthForm">
                <label class="form-label small mb-0 text-muted">Mes</label>
                <input type="month" name="month" value="{{ $month }}"
                       class="form-control form-control-sm" style="width:auto"
                       onchange="document.getElementById('monthForm').submit()">
            </form>
            <button class="btn btn-brand" onclick="SF.openSlotAdd()">
                <i class="fa-solid fa-plus me-1"></i> Agregar clase
            </button>
        </div>
    </div>

    @if ($months->count() > 1)
        <div class="mb-3 d-flex flex-wrap gap-1">
            @foreach ($months as $m)
                <a href="{{ route('schedule.template', ['month' => $m->key_month]) }}"
                   class="btn btn-sm {{ $m->key_month === $month ? 'btn-brand' : 'btn-outline-secondary' }}">
                    {{ $m->display_label }}
                </a>
            @endforeach
        </div>
    @endif

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
                        <div class="session-card slot-card chip-{{ $slot->program?->color ?? 'teal' }}"
                             role="button" tabindex="0"
                             onclick="SF.openSlotActions(this)"
                             onkeydown="if(event.key==='Enter'){SF.openSlotActions(this)}"
                             data-slot-id="{{ $slot->id }}"
                             data-program="{{ $slot->program_id }}"
                             data-program-name="{{ $slot->program?->name }}"
                             data-level-id="{{ $slot->level_id }}"
                             data-weekday="{{ $slot->weekday }}"
                             data-start="{{ \Illuminate\Support\Str::of($slot->start_time)->substr(0,5) }}"
                             data-lane="{{ $slot->lane_id }}"
                             data-instructor="{{ $slot->instructor_id }}"
                             data-instructor2="{{ $slot->instructor_id_2 }}"
                             data-duration="{{ $slot->duration_min }}"
                             data-roster-ids="{{ $slot->members->pluck('id')->implode(',') }}"
                             data-roster-url="{{ route('schedule.slots.roster', $slot) }}"
                             data-update-url="{{ route('schedule.slots.update', $slot) }}"
                             data-destroy-url="{{ route('schedule.slots.destroy', $slot) }}">
                            <div class="d-flex justify-content-between">
                                <span class="mono small fw-600">{{ \Illuminate\Support\Str::of($slot->start_time)->substr(0,5) }}</span>
                                <span class="small text-muted">{{ $slot->duration_min }}′</span>
                            </div>
                            <div class="fw-600 small">{{ $slot->program?->name }}</div>
                            @if ($slot->level)<div class="small" style="color:var(--brand-teal-600)">{{ $slot->level->name }}</div>@endif
                            <div class="small text-muted">{{ $slot->lane?->label ?? 'Sin carril' }}</div>
                            <div class="small">{{ $slot->instructor?->name ?? 'Sin instructor' }}@if ($slot->instructor2) <span class="text-muted">+ {{ $slot->instructor2->name }}</span>@endif</div>
                            <div class="small text-muted mt-1">
                                <i class="fa-solid fa-users"></i> {{ $slot->members->count() }} en roster
                            </div>
                            <div class="d-flex gap-1 mt-2">
                                <a href="{{ route('schedule.slots.roster', $slot) }}"
                                   class="btn btn-sm btn-outline-secondary flex-fill"
                                   onclick="event.stopPropagation()">Roster</a>
                                <form method="POST" action="{{ route('schedule.slots.destroy', $slot) }}"
                                      onclick="event.stopPropagation()"
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

    {{-- Modal agregar/editar clase en la plantilla --}}
    <div class="modal fade" id="slotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('schedule.slots.store') }}" class="modal-content app-card"
                  id="slotForm" data-store-url="{{ route('schedule.slots.store') }}">
                @csrf
                <input type="hidden" name="_method" id="slot-method" value="POST">
                <input type="hidden" name="month" value="{{ $month }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="slotModalTitle">Agregar clase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Programa *</label>
                        <select name="program_id" id="slot-program" class="form-select" required>
                            @foreach ($programs as $p)
                                <option value="{{ $p->id }}" data-duration="{{ $p->duration_min }}">
                                    {{ $p->name }} ({{ $p->duration_min }}′, cupo {{ $p->lane_capacity }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="slot-level-wrap">
                        <label class="form-label small">Nivel</label>
                        <select name="level_id" id="slot-level" class="form-select">
                            <option value="">— Sin nivel —</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Día *</label>
                            <select name="weekday" id="slot-weekday" class="form-select" required>
                                @foreach ($weekdays as $iso => $label)
                                    <option value="{{ $iso }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Hora *</label>
                            <input type="time" name="start_time" id="slot-start" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="form-label small">Carril</label>
                            <select name="lane_id" id="slot-lane" class="form-select">
                                <option value="">— Sin asignar —</option>
                                @foreach ($lanes as $lane)
                                    <option value="{{ $lane->id }}">{{ $lane->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Instructor</label>
                            <select name="instructor_id" id="slot-instructor" class="form-select">
                                <option value="">— Sin asignar —</option>
                                @foreach ($instructors as $i)
                                    <option value="{{ $i->id }}">{{ $i->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Segundo maestro (opcional)</label>
                        <select name="instructor_id_2" id="slot-instructor-2" class="form-select">
                            <option value="">— Ninguno —</option>
                            @foreach ($instructors as $i)
                                <option value="{{ $i->id }}">{{ $i->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-2">
                        <label class="form-label small">Duración (min) — vacío = la del programa</label>
                        <input type="number" name="duration_min" id="slot-duration" class="form-control" min="5" max="240">
                    </div>

                    {{-- Elegir socios del roster al crear/editar la clase --}}
                    <div class="mt-3">
                        <label class="form-label small d-flex justify-content-between">
                            <span>Socios en la clase</span>
                            <span class="text-muted" id="slot-roster-count">0</span>
                        </label>
                        <input type="text" class="form-control form-control-sm mb-2"
                               placeholder="Buscar socio…" oninput="SF.filterSlotMembers(this)">
                        <div id="slot-members" class="slot-member-list">
                            @foreach ($members as $m)
                                <label class="roster-item d-flex align-items-center gap-2 py-1 px-2 rounded"
                                       data-text="{{ mb_strtolower($m->first_name.' '.$m->last_name_1.' '.$m->last_name_2.' '.$m->socio_number) }}">
                                    <input type="checkbox" name="member_ids[]" value="{{ $m->id }}" class="form-check-input mt-0"
                                           onchange="SF.updateSlotRosterCount()">
                                    <span class="mono small text-muted" style="width:52px">{{ $m->socio_number }}</span>
                                    <span class="small">{{ trim("$m->first_name $m->last_name_1 $m->last_name_2") }}</span>
                                </label>
                            @endforeach
                        </div>
                        <small class="text-muted">Opcional. También puedes gestionarlo luego con "Roster" o arrastrando socios en el horario.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand" id="slotSubmitBtn">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Form oculto para "Quitar" desde la hoja de acciones --}}
    <form method="POST" id="slotDeleteForm" class="d-none">
        @csrf @method('DELETE')
    </form>

    @include('schedule.partials.program-levels-payload')
@endsection
