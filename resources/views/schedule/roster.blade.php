@extends('layouts.app')
@section('title', 'Roster — Swim Fitness')

@section('content')

    <div class="mb-4">
        <a href="{{ route('schedule.template') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Plantilla
        </a>
        <h1 class="h3 mt-2 mb-1">Roster de la clase</h1>
        <p class="text-muted mb-0">
            {{ $slot->program?->name }} ·
            {{ \App\Enums\Weekday::tryFrom($slot->weekday)?->label() ?? 'Día '.$slot->weekday }} ·
            {{ \Illuminate\Support\Str::of($slot->start_time)->substr(0,5) }}
            · cupo del programa: {{ $slot->program?->lane_capacity }}
        </p>
    </div>

    <form method="POST" action="{{ route('schedule.slots.roster.update', $slot) }}"
          class="app-card p-4" style="max-width:720px">
        @csrf @method('PUT')

        <div class="mb-3">
            <input type="text" class="form-control" placeholder="Filtrar socios…"
                   oninput="SF.filterRoster(this)">
        </div>

        @php $assigned = $slot->members->pluck('id')->flip(); @endphp
        <div id="roster-list" style="max-height:460px; overflow:auto">
            @foreach ($members as $m)
                <label class="roster-item d-flex align-items-center gap-2 py-1 px-2 rounded"
                       data-text="{{ mb_strtolower($m->last_name_1.' '.$m->first_name.' '.$m->socio_number) }}">
                    <input type="checkbox" name="member_ids[]" value="{{ $m->id }}"
                           class="form-check-input mt-0"
                           @checked($assigned->has($m->id))>
                    <span class="mono small text-muted" style="width:56px">{{ $m->socio_number }}</span>
                    <span class="small">{{ trim("$m->first_name $m->last_name_1 $m->last_name_2") }}</span>
                </label>
            @endforeach
        </div>

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-brand">Guardar roster</button>
            <a href="{{ route('schedule.template') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
        <p class="small text-muted mt-2 mb-0">
            Las sesiones futuras heredarán este roster. Si superas el cupo del programa, se guardará igual pero verás un aviso en el horario.
        </p>
    </form>
@endsection
