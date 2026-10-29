@extends('layouts.app')
@section('title', ($program->exists ? 'Editar' : 'Nuevo') . ' programa — Swim Fitness')
@section('content')

    <div class="mb-4">
        <a href="{{ route('programs.index') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Programas
        </a>
        <h1 class="h3 mt-2 mb-0">{{ $program->exists ? 'Editar programa' : 'Nuevo programa' }}</h1>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    <div class="row g-3">
        {{-- Datos del programa --}}
        <div class="col-lg-7">
            <form method="POST"
                  action="{{ $program->exists ? route('programs.update', $program) : route('programs.store') }}"
                  class="app-card p-4">
                @csrf
                @if ($program->exists) @method('PUT') @endif

                <div class="mb-3">
                    <label class="form-label small">Nombre *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $program->name) }}" required>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Audiencia *</label>
                        <select name="audience" class="form-select">
                            <option value="kids"   @selected(old('audience', $program->audience) === 'kids')>Niños</option>
                            <option value="adults" @selected(old('audience', $program->audience) === 'adults')>Adultos</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Rango de edad</label>
                        <input type="text" name="age_range" class="form-control" value="{{ old('age_range', $program->age_range) }}" placeholder="4 a 14 años">
                    </div>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-4">
                        <label class="form-label small">Duración (min) *</label>
                        <input type="number" name="duration_min" class="form-control mono" min="5" max="240" value="{{ old('duration_min', $program->duration_min) }}" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Cupo / carril *</label>
                        <input type="number" name="lane_capacity" class="form-control mono" min="1" max="50" value="{{ old('lane_capacity', $program->lane_capacity) }}" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Color *</label>
                        <select name="color" class="form-select">
                            @foreach (['teal' => 'Teal', 'green' => 'Verde', 'blue' => 'Azul'] as $v => $txt)
                                <option value="{{ $v }}" @selected(old('color', $program->color ?? 'teal') === $v)>{{ $txt }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-2">
                    <label class="form-label small">Resumen</label>
                    <textarea name="summary" class="form-control" rows="2">{{ old('summary', $program->summary) }}</textarea>
                </div>

                <div class="form-check my-3">
                    <input class="form-check-input" type="checkbox" name="active" id="active" value="1"
                           @checked(old('active', $program->active ?? true))>
                    <label class="form-check-label small" for="active">Activo</label>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-brand">{{ $program->exists ? 'Guardar cambios' : 'Crear programa' }}</button>
                    <a href="{{ route('programs.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>

                @unless ($program->exists)
                    <p class="small text-muted mt-3 mb-0">Después de crearlo podrás agregarle niveles (p. ej. para Swim Junior).</p>
                @endunless
            </form>
        </div>

        {{-- Niveles (solo al editar) --}}
        @if ($program->exists)
            <div class="col-lg-5">
                <div class="app-card p-4 h-100">
                    <h2 class="h6 mb-1">Niveles</h2>
                    <p class="small text-muted">El nivel define nombre y duración de la clase. El cupo lo pone el programa.</p>

                    <div class="d-flex flex-column gap-2 mb-3">
                        @forelse ($program->levels as $level)
                            <div class="d-flex align-items-center gap-2">
                                <form method="POST" action="{{ route('programs.levels.update', $level) }}"
                                      class="d-flex align-items-center gap-2 flex-grow-1">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" value="{{ $level->name }}" class="form-control form-control-sm flex-grow-1" required>
                                    <input type="number" name="duration_min" value="{{ $level->duration_min }}" class="form-control form-control-sm mono" style="width:72px" min="5" max="240" required>
                                    <button class="btn btn-sm btn-soft" title="Guardar nombre/duración"><i class="fa-solid fa-check"></i></button>
                                </form>
                                <form method="POST" action="{{ route('programs.levels.destroy', $level) }}"
                                      onsubmit="return confirm('¿Eliminar este nivel?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        @empty
                            <p class="small text-muted mb-0">Sin niveles. Agrega uno abajo.</p>
                        @endforelse
                    </div>

                    <hr style="border-color:var(--border)">
                    <form method="POST" action="{{ route('programs.levels.store', $program) }}">
                        @csrf
                        <label class="form-label small">Agregar nivel</label>
                        <div class="d-flex gap-2">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Nombre (p. ej. Torpedos)" required>
                            <input type="number" name="duration_min" class="form-control form-control-sm mono" style="width:76px" placeholder="min" min="5" max="240" required>
                            <button class="btn btn-sm btn-brand"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
