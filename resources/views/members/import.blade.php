@extends('layouts.app')
@section('title', 'Importar socios — Swim Fitness')
@section('content')

    <div class="mb-4">
        <a href="{{ route('members.index') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Socios
        </a>
        <h1 class="h3 mt-2 mb-0">Importar socios desde Excel</h1>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0 small">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-7">
            <form method="POST" action="{{ route('members.import.store') }}"
                  enctype="multipart/form-data" class="app-card p-4">
                @csrf
                <label class="form-label small">Archivo (.xlsx o .xls)</label>
                <input type="file" name="file" class="form-control mb-3" accept=".xlsx,.xls" required>
                <button class="btn btn-brand">
                    <i class="fa-solid fa-file-import me-1"></i> Importar
                </button>
            </form>
        </div>

        <div class="col-md-5">
            <div class="app-card p-4 h-100">
                <h2 class="h6 text-muted mb-3">Cómo funciona</h2>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Se busca la fila de encabezados automáticamente (columna "Número de socio").</li>
                    <li>Los socios se identifican por su número: re-importar <strong>actualiza</strong>, no duplica.</li>
                    <li>Los tipos de socio nuevos se crean solos.</li>
                    <li>Se ignoran filas en blanco y el placeholder "X" en el segundo apellido.</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
