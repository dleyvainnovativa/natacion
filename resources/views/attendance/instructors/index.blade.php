@extends('layouts.app')
@section('title', 'Asistencia de instructores — Swim Fitness')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Asistencia de instructores</h1>
            <p class="text-muted mb-0">{{ $date->isoFormat('dddd D [de] MMMM') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('attendance.instructors.index', ['date' => $prevDate]) }}"
               class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
            <a href="{{ route('attendance.instructors.index') }}" class="btn btn-outline-secondary btn-sm">Hoy</a>
            <a href="{{ route('attendance.instructors.index', ['date' => $nextDate]) }}"
               class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>

    <p class="text-muted small mb-3">
        Si un instructor llega tarde o falta, asigna un suplente: esa clase pasará a contar
        para el pago del suplente.
    </p>

    @php $wholeDayBlackout = $blackouts->firstWhere('start_min', null); @endphp
    @if ($blackouts->isNotEmpty())
        <div class="alert alert-secondary py-2">
            <i class="fa-solid fa-ban me-1"></i>
            @if ($wholeDayBlackout)
                Día {{ strtolower($wholeDayBlackout->label()) }}: las clases de este día no cuentan para el pago.
            @else
                Hay horarios cerrados este día (mantenimiento/festivo): esas clases no cuentan para el pago.
            @endif
        </div>
    @endif

    <div class="app-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">Hora</th>
                        <th>Programa</th>
                        <th>Instructor programado</th>
                        <th>Estado</th>
                        <th>Suplente</th>
                        <th>Impartió</th>
                        <th class="text-center">Evaluaciones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        @php
                            $att = $session->instructorAttendance;
                            $blk = \App\Models\Blackout::covers($session, $blackouts);
                        @endphp
                        <tr data-session="{{ $session->id }}" class="{{ $blk ? 'opacity-50' : '' }}">
                            <td class="ps-3 mono">{{ $session->starts_at->format('H:i') }}</td>
                            <td class="small">
                                {{ $session->program?->name }}
                                <div class="text-muted" style="font-size:.8em">
                                    {{ $session->lane?->label ?? 'Sin carril' }}
                                    @if (($session->collapsed_count ?? 1) > 1)
                                        <span class="badge bg-secondary ms-1" title="Se agruparon {{ $session->collapsed_count }} registros del mismo carril y hora">
                                            +{{ $session->collapsed_count - 1 }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="small">
                                {{ $session->scheduledInstructor?->name ?? '—' }}
                                @if ($session->instructor2)
                                    <div class="text-muted" style="font-size:.8em">+ {{ $session->instructor2->name }} (co-maestro)</div>
                                @endif
                            </td>
                            <td>
                                <select class="form-select form-select-sm att-status" style="min-width:110px">
                                    <option value="on_time" @selected($att?->status==='on_time')>Puntual</option>
                                    <option value="late"    @selected($att?->status==='late')>Tarde</option>
                                    <option value="absent"  @selected($att?->status==='absent')>Ausente</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm att-sub" style="min-width:130px">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($instructors as $i)
                                        @continue($i->id === $session->scheduled_instructor_id)
                                        <option value="{{ $i->id }}"
                                            @selected($att?->substitute_instructor_id===$i->id)>{{ $i->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="small att-actual">
                                {{ $session->actualInstructor?->name ?? '—' }}
                            </td>
                            <td class="text-center">
                                <input type="number" min="0" max="99" value="{{ $session->evaluations_count ?? 0 }}"
                                       class="form-control form-control-sm mono att-evals text-center"
                                       style="width:70px;margin:0 auto" title="Evaluaciones en esta clase">
                            </td>
                            <td class="text-end pe-3">
                                @if ($blk)
                                    <span class="badge bg-secondary" title="Festivo / mantenimiento: no cuenta">No cuenta</span>
                                @else
                                    <button class="btn btn-sm btn-brand"
                                            onclick="SF.saveInstructorAttendance(this)">
                                        Guardar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                No hay clases con instructor asignado este día.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
