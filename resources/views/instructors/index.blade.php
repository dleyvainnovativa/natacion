@extends('layouts.app')
@section('title', 'Maestros — Swim Fitness')
@section('content')

    <div class="page-head">
        <div>
            <div class="page-eyebrow"><i class="fa-solid fa-chalkboard-user"></i> Catálogo</div>
            <h1 class="page-title">Maestros</h1>
            <p class="page-sub">{{ $instructors->where('active', true)->count() }} activos de {{ $instructors->count() }}.</p>
        </div>
        <a href="{{ route('instructors.create') }}" class="btn btn-brand">
            <i class="fa-solid fa-plus me-1"></i> Nuevo maestro
        </a>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    <div class="app-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">Nombre</th>
                        <th class="text-end">Pago / clase</th>
                        <th class="text-center">Clases en plantilla</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($instructors as $i)
                        <tr class="{{ $i->active ? '' : 'opacity-50' }}">
                            <td class="ps-3 fw-500">{{ $i->name }}</td>
                            <td class="text-end mono">{{ $i->pay_per_class ? '$' . number_format($i->pay_per_class, 2) : '—' }}</td>
                            <td class="text-center">{{ $i->slots_count }}</td>
                            <td>
                                <span class="badge-role">{{ $i->active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('instructors.edit', $i) }}" class="btn btn-outline-secondary" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <form method="POST" action="{{ route('instructors.toggle', $i) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-outline-secondary" title="{{ $i->active ? 'Desactivar' : 'Activar' }}">
                                            <i class="fa-solid {{ $i->active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">Aún no hay maestros. Agrega el primero.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
