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
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        @php $att = $session->instructorAttendance; @endphp
                        <tr data-session="{{ $session->id }}">
                            <td class="ps-3 mono">{{ $session->starts_at->format('H:i') }}</td>
                            <td class="small">{{ $session->program?->name }}</td>
                            <td class="small">{{ $session->scheduledInstructor?->name ?? '—' }}</td>
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
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-brand"
                                        onclick="SF.saveInstructorAttendance(this)">
                                    Guardar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                No hay clases con instructor asignado este día.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
