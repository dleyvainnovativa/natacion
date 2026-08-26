@extends('layouts.app')
@section('title', 'Asistencia de alumnos — Swim Fitness')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Asistencia de alumnos</h1>
            <p class="text-muted mb-0">{{ $date->isoFormat('dddd D [de] MMMM') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('attendance.members.index', ['date' => $prevDate]) }}"
               class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
            <a href="{{ route('attendance.members.index') }}" class="btn btn-outline-secondary btn-sm">Hoy</a>
            <a href="{{ route('attendance.members.index', ['date' => $nextDate]) }}"
               class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    <div class="app-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">Hora</th>
                        <th>Programa</th>
                        <th>Carril</th>
                        <th>Instructor</th>
                        <th>Alumnos</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        @php
                            $marked = $session->memberAttendances->count();
                            $total  = $session->members->count();
                        @endphp
                        <tr>
                            <td class="ps-3 mono">{{ $session->starts_at->format('H:i') }}</td>
                            <td>{{ $session->program?->name }}</td>
                            <td class="small text-muted">{{ $session->lane?->label ?? '—' }}</td>
                            <td class="small">{{ $session->actualInstructor?->name ?? '—' }}</td>
                            <td class="small">
                                @if ($marked)
                                    <span class="badge-role">{{ $marked }}/{{ $total }} marcados</span>
                                @else
                                    <span class="text-muted">{{ $total }} en roster</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('attendance.members.show', $session) }}"
                                   class="btn btn-sm btn-brand">
                                    <i class="fa-solid fa-user-check me-1"></i> Pasar lista
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                No hay clases asignadas para este día.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
