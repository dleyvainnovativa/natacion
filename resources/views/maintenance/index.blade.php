@extends('layouts.app')
@section('title', 'Mantenimiento — Swim Fitness')
@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Mantenimiento</h1>
            <p class="text-muted mb-0">{{ $openCount }} tareas pendientes.</p>
        </div>
        <button class="btn btn-brand" onclick="SF.modal.show('maintModal')">
            <i class="fa-solid fa-plus me-1"></i> Nueva tarea
        </button>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    {{-- Filtro por estado --}}
    <div class="btn-group mb-3" role="group">
        @foreach (['open' => 'Pendientes', 'done' => 'Hechas', 'all' => 'Todas'] as $key => $label)
            <a href="{{ route('maintenance.index', ['status' => $key]) }}"
               class="btn btn-sm {{ $filter === $key ? 'btn-brand' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="app-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3" style="width:44px"></th>
                        <th>Tarea</th>
                        <th>Alberca</th>
                        <th>Programada</th>
                        <th>Registró</th>
                        <th class="pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="{{ $log->isDone() ? 'opacity-50' : '' }}">
                            <td class="ps-3">
                                <form method="POST" action="{{ route('maintenance.toggle', $log) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm {{ $log->isDone() ? 'btn-success' : 'btn-outline-secondary' }}"
                                            title="{{ $log->isDone() ? 'Reabrir' : 'Marcar hecha' }}">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="{{ $log->isDone() ? 'text-decoration-line-through' : 'fw-500' }}">
                                    {{ $log->title }}
                                </div>
                                @if ($log->notes)
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($log->notes, 80) }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $log->pool?->name ?? '—' }}</td>
                            <td class="small">
                                @if ($log->scheduled_for)
                                    <span class="{{ $log->isOverdue() ? 'text-danger fw-600' : '' }}">
                                        {{ $log->scheduled_for->format('d/m/Y') }}
                                        @if ($log->isOverdue()) (vencida) @endif
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $log->creator?->name ?? '—' }}</td>
                            <td class="text-end pe-3">
                                <form method="POST" action="{{ route('maintenance.destroy', $log) }}"
                                      onsubmit="return confirm('¿Eliminar esta tarea?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            No hay tareas {{ $filter === 'done' ? 'hechas' : ($filter === 'open' ? 'pendientes' : '') }}.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>

    {{-- Modal nueva tarea --}}
    <div class="modal fade" id="maintModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('maintenance.store') }}" class="modal-content app-card">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nueva tarea de mantenimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Tarea *</label>
                        <input type="text" name="title" class="form-control" required
                               placeholder="Ej. Cambiar filtro de la bomba">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Alberca</label>
                            <select name="pool_id" class="form-select">
                                <option value="">— General —</option>
                                @foreach ($pools as $pool)
                                    <option value="{{ $pool->id }}">{{ $pool->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Fecha programada</label>
                            <input type="date" name="scheduled_for" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small">Notas</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand">Registrar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
