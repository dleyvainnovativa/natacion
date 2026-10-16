@extends('layouts.app')
@section('title', 'Inscribir a clases — ' . $member->fullName())
@section('content')

    <div class="mb-4">
        <a href="{{ route('members.show', $member) }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> {{ $member->fullName() }}
        </a>
        <h1 class="h3 mt-2 mb-0">Inscribir a clases</h1>
        <p class="page-sub">
            Socio #{{ $member->socio_number }}
            @if ($member->membershipType)
                · {{ $member->membershipType->raw_label }}
                @if ($member->entitledDaysPerWeek()) ({{ $member->entitledDaysPerWeek() }} días/semana) @endif
            @endif
        </p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('members.enroll.store', $member) }}" class="app-card p-4" style="max-width:820px">
        @csrf

        <p class="text-muted small">
            Marca las clases del socio. Quedan inscritas en la serie recurrente y
            en las sesiones de esta semana en adelante; desmarcar lo quita.
        </p>

        @include('members.partials.slot-picker', ['slots' => $slots, 'selected' => $selected, 'pickerMode' => 'manage'])

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-brand"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar inscripción</button>
            <a href="{{ route('members.show', $member) }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
