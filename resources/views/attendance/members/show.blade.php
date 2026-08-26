@extends('layouts.app')
@section('title', 'Pasar lista — Swim Fitness')

@section('content')

    <div class="mb-4">
        <a href="{{ route('attendance.members.index', ['date' => $session->starts_at->toDateString()]) }}"
           class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Asistencia
        </a>
        <h1 class="h3 mt-2 mb-1">{{ $session->program?->name }}</h1>
        <p class="text-muted mb-0">
            {{ $session->starts_at->isoFormat('dddd D MMM, HH:mm') }} ·
            {{ $session->lane?->label ?? 'Sin carril' }} ·
            {{ $session->actualInstructor?->name ?? 'Sin instructor' }}
        </p>
    </div>

    @if ($session->members->isEmpty())
        <div class="app-card p-5 text-center text-muted">
            Esta clase no tiene socios en el roster. Asígnalos desde la plantilla del horario.
        </div>
    @else
        <form method="POST" action="{{ route('attendance.members.store', $session) }}"
              class="app-card p-4" style="max-width:640px">
            @csrf

            {{-- Marcar todos presentes de un toque --}}
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="document.querySelectorAll('input[value=present]').forEach(r=>r.checked=true)">
                    Todos presentes
                </button>
            </div>

            <div class="d-flex flex-column gap-1">
                @foreach ($session->members as $member)
                    @php $status = $existing->get($member->id)?->status ?? 'present'; @endphp
                    <div class="d-flex align-items-center justify-content-between py-2 px-2 rounded"
                         style="border:1px solid var(--border)">
                        <div class="small">
                            <span class="mono text-muted me-2">{{ $member->socio_number }}</span>
                            {{ trim("$member->first_name $member->last_name_1 $member->last_name_2") }}
                        </div>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="attendance[{{ $member->id }}]"
                                   id="p{{ $member->id }}" value="present" @checked($status==='present')>
                            <label class="btn btn-outline-success" for="p{{ $member->id }}">Presente</label>

                            <input type="radio" class="btn-check" name="attendance[{{ $member->id }}]"
                                   id="a{{ $member->id }}" value="absent" @checked($status==='absent')>
                            <label class="btn btn-outline-danger" for="a{{ $member->id }}">Ausente</label>

                            <input type="radio" class="btn-check" name="attendance[{{ $member->id }}]"
                                   id="e{{ $member->id }}" value="excused" @checked($status==='excused')>
                            <label class="btn btn-outline-secondary" for="e{{ $member->id }}">Justif.</label>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-brand">Guardar asistencia</button>
                <a href="{{ route('attendance.members.index', ['date' => $session->starts_at->toDateString()]) }}"
                   class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    @endif
@endsection
