{{-- Tarjeta de clase para la rejilla. Draggable (mover clase completa) y con
     botón para gestionar socios (mover socio individual). --}}
@php
    $color = $session->program?->color ?? 'teal';
    $over  = $session->isOverCapacity();
    $subbed = $session->actual_instructor_id
           && $session->actual_instructor_id !== $session->scheduled_instructor_id;
@endphp
<div class="session-card chip-{{ $color }} {{ $session->status === 'cancelled' ? 'is-cancelled' : '' }}"
     @can('move-classes')
         data-session-id="{{ $session->id }}"
         data-starts="{{ $session->starts_at->format('Y-m-d\TH:i') }}"
         data-lane="{{ $session->lane_id }}"
         data-instructor="{{ $session->scheduled_instructor_id }}"
     @endcan
>
    <div class="d-flex justify-content-between align-items-start">
        <span class="fw-600 small">{{ $session->program?->name ?? 'Clase' }}</span>
        @if ($subbed)<span class="badge-role" title="Suplente">supl.</span>@endif
    </div>
    <div class="sc-meta">
        <span><i class="fa-solid fa-user"></i> {{ $session->actualInstructor?->name ?? '—' }}</span>
        <span><i class="fa-solid fa-location-dot"></i> {{ $session->lane?->label ?? '—' }}</span>
    </div>
    <div class="sc-foot">
        <span class="{{ $over ? 'text-warning fw-600' : 'text-muted' }}">
            <i class="fa-solid fa-users"></i> {{ $session->members->count() }}@if($session->program) / {{ $session->program->lane_capacity }}@endif
        </span>
        @can('move-classes')
            <span class="sc-actions">
                <button type="button" class="sc-btn" title="Mover clase (o arrástrala)"
                        onclick="SF.openMove(this.closest('.session-card'))">
                    <i class="fa-solid fa-up-down-left-right"></i>
                </button>
                <button type="button" class="sc-btn" title="Mover un socio"
                        onclick="SF.openMoveMember({{ $session->id }})">
                    <i class="fa-solid fa-user-pen"></i>
                </button>
            </span>
        @endcan
    </div>
    @if ($session->status === 'cancelled')
        <div class="small text-danger mt-1">Cancelada</div>
    @endif
</div>
