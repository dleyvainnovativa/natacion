@extends('layouts.app')
@section('title', 'Horario — Swim Fitness')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Horario semanal</h1>
            <p class="text-muted mb-0">
                Semana del {{ $weekStart->format('d/m/Y') }}
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('schedule.index', ['week' => $prevWeek]) }}"
               class="btn btn-outline-secondary btn-sm" title="Semana anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a href="{{ route('schedule.index') }}" class="btn btn-outline-secondary btn-sm">Hoy</a>
            <a href="{{ route('schedule.index', ['week' => $nextWeek]) }}"
               class="btn btn-outline-secondary btn-sm" title="Semana siguiente">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            @can('move-classes')
                <a href="{{ route('schedule.template') }}" class="btn btn-brand btn-sm ms-2">
                    <i class="fa-solid fa-table-cells me-1"></i> Editar plantilla
                </a>
            @endcan
        </div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    <div class="schedule-grid">
        @foreach ($weekdays as $iso => $label)
            @php $dayDate = $weekStart->copy()->addDays($iso - 1); @endphp
            <div class="schedule-col" data-date="{{ $dayDate->format('Y-m-d') }}">
                <div class="schedule-col-head">
                    <span class="fw-600">{{ $label }}</span>
                    <span class="text-muted small">{{ $dayDate->format('d/m') }}</span>
                </div>

                <div class="schedule-col-body">
                    @forelse ($sessions->get($iso, collect()) as $session)
                        @php
                            $color = $session->program?->color ?? 'teal';
                            $over  = $session->isOverCapacity();
                            $subbed = $session->actual_instructor_id
                                   && $session->actual_instructor_id !== $session->scheduled_instructor_id;
                        @endphp
                        <div class="session-card chip-{{ $color }} {{ $session->status === 'cancelled' ? 'is-cancelled' : '' }}"
                             @can('move-classes')
                                 role="button"
                                 data-session-id="{{ $session->id }}"
                                 data-starts="{{ $session->starts_at->format('Y-m-d\TH:i') }}"
                                 data-lane="{{ $session->lane_id }}"
                                 data-instructor="{{ $session->scheduled_instructor_id }}"
                                 onclick="SF.openMove(this)"
                             @endcan
                        >
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="mono small fw-600">{{ $session->starts_at->format('H:i') }}</span>
                                <span class="small text-muted">{{ $session->duration_min }}′</span>
                            </div>
                            <div class="fw-600 small">{{ $session->program?->name }}</div>
                            <div class="small text-muted">
                                <i class="fa-solid fa-location-dot"></i> {{ $session->lane?->label ?? 'Sin carril' }}
                            </div>
                            <div class="small">
                                <i class="fa-solid fa-user"></i>
                                {{ $session->actualInstructor?->name ?? 'Sin instructor' }}
                                @if ($subbed)
                                    <span class="badge-role ms-1" title="Suplente">supl.</span>
                                @endif
                            </div>
                            <div class="small text-muted d-flex justify-content-between mt-1">
                                <span><i class="fa-solid fa-users"></i> {{ $session->members->count() }}</span>
                                @if ($over)
                                    <span class="text-warning" title="Sobre cupo">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                    </span>
                                @endif
                                @if ($session->is_modified)
                                    <span title="Modificada"><i class="fa-solid fa-pen-to-square"></i></span>
                                @endif
                            </div>
                            @if ($session->status === 'cancelled')
                                <div class="small text-danger mt-1">Cancelada</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small text-center py-3">—</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    @can('move-classes')
        @include('schedule.partials.move-modal')
    @endcan
@endsection

@push('styles')
@endpush
