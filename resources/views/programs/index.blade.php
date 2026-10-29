@extends('layouts.app')
@section('title', 'Programas — Swim Fitness')
@section('content')

    <div class="page-head">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-layer-group"></i> Catálogo</div>
            <h1 class="page-title">Programas</h1>
            <p class="page-sub">Disciplinas, su duración, cupo y niveles.</p>
        </div>
        <a href="{{ route('programs.create') }}" class="btn btn-brand">
            <i class="fa-solid fa-plus me-1"></i> Nuevo programa
        </a>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    <div class="row g-3">
        @foreach ($programs as $p)
            <div class="col-md-6 col-xl-4">
                <div class="app-card p-3 h-100 {{ $p->active ? '' : 'opacity-50' }}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="chip-{{ $p->color ?? 'teal' }} slot-prog-chip">{{ $p->name }}</span>
                        <span class="badge-role">{{ $p->active ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                    <div class="small text-muted mb-2">
                        {{ $p->audience === 'kids' ? 'Niños' : 'Adultos' }} ·
                        {{ $p->duration_min }}′ · cupo {{ $p->lane_capacity }}/carril
                    </div>

                    @if ($p->levels->count())
                        <div class="small mb-2">
                            <span class="text-muted">Niveles:</span>
                            {{ $p->levels->pluck('name')->join(', ') }}
                        </div>
                    @endif

                    <div class="d-flex gap-2 mt-2">
                        <a href="{{ route('programs.edit', $p) }}" class="btn btn-sm btn-soft flex-fill">
                            <i class="fa-solid fa-pen me-1"></i> Editar{{ $p->audience === 'kids' ? ' / niveles' : '' }}
                        </a>
                        <form method="POST" action="{{ route('programs.toggle', $p) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-secondary" title="{{ $p->active ? 'Desactivar' : 'Activar' }}">
                                <i class="fa-solid {{ $p->active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
