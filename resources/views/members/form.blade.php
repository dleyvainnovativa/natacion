@extends('layouts.app')
@section('title', ($member->exists ? 'Editar' : 'Nuevo') . ' socio — Swim Fitness')
@section('content')

    <div class="mb-4">
        <a href="{{ route('members.index') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Socios
        </a>
        <h1 class="h3 mt-2 mb-0">{{ $member->exists ? 'Editar socio' : 'Nuevo socio' }}</h1>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0 small">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}"
          class="app-card p-4" style="max-width:760px">
        @csrf
        @if ($member->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small">Número de socio *</label>
                <input type="number" name="socio_number" class="form-control mono"
                       value="{{ old('socio_number', $member->socio_number) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Estado *</label>
                <input type="text" name="status" class="form-control"
                       value="{{ old('status', $member->status) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Cuota</label>
                <input type="number" step="0.01" name="fee" class="form-control mono"
                       value="{{ old('fee', $member->fee) }}">
            </div>

            <div class="col-md-4">
                <label class="form-label small">Nombre *</label>
                <input type="text" name="first_name" class="form-control"
                       value="{{ old('first_name', $member->first_name) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Apellido 1 *</label>
                <input type="text" name="last_name_1" class="form-control"
                       value="{{ old('last_name_1', $member->last_name_1) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Apellido 2</label>
                <input type="text" name="last_name_2" class="form-control"
                       value="{{ old('last_name_2', $member->last_name_2) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label small">Teléfono</label>
                <input type="text" name="phone" class="form-control"
                       value="{{ old('phone', $member->phone) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Correo</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email', $member->email) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label small">Tipo de socio</label>
                <select name="membership_type_id" class="form-select">
                    <option value="">— Sin asignar —</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}"
                            @selected(old('membership_type_id', $member->membership_type_id) == $type->id)>
                            {{ $type->raw_label }}
                            @if ($type->days_per_week) ({{ $type->days_per_week }} días) @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Próximo pago</label>
                <input type="date" name="next_billing_date" class="form-control"
                       value="{{ old('next_billing_date', $member->next_billing_date?->format('Y-m-d')) }}">
            </div>

            <div class="col-12">
                <label class="form-label small">Notas</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $member->notes) }}</textarea>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-brand">{{ $member->exists ? 'Guardar cambios' : 'Registrar socio' }}</button>
            <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            @if ($member->exists)
                <button type="submit" form="delete-form"
                        class="btn btn-outline-danger ms-auto"
                        onclick="return confirm('¿Dar de baja a este socio?')">
                    Dar de baja
                </button>
            @endif
        </div>
    </form>

    @if ($member->exists)
        <form id="delete-form" method="POST" action="{{ route('members.destroy', $member) }}" class="d-none">
            @csrf @method('DELETE')
        </form>
    @endif
@endsection
