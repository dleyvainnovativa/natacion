@extends('layouts.app')
@section('title', ($instructor->exists ? 'Editar' : 'Nuevo') . ' maestro — Swim Fitness')
@section('content')

    <div class="mb-4">
        <a href="{{ route('instructors.index') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Maestros
        </a>
        <h1 class="h3 mt-2 mb-0">{{ $instructor->exists ? 'Editar maestro' : 'Nuevo maestro' }}</h1>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST"
          action="{{ $instructor->exists ? route('instructors.update', $instructor) : route('instructors.store') }}"
          class="app-card p-4" style="max-width:560px">
        @csrf
        @if ($instructor->exists) @method('PUT') @endif

        <div class="mb-3">
            <label class="form-label small">Nombre *</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $instructor->name) }}" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label small">Pago por clase impartida</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" name="pay_per_class" class="form-control mono"
                       value="{{ old('pay_per_class', $instructor->pay_per_class) }}">
            </div>
            <small class="text-muted">Se usa para el reporte de nómina.</small>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="active" id="active" value="1"
                   @checked(old('active', $instructor->active ?? true))>
            <label class="form-check-label small" for="active">Activo (aparece al agendar clases)</label>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-brand">{{ $instructor->exists ? 'Guardar cambios' : 'Agregar maestro' }}</button>
            <a href="{{ route('instructors.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
