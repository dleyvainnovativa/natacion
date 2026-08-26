@extends('layouts.app')
@section('title', 'Socios — Swim Fitness')
@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Socios</h1>
            <p class="text-muted mb-0">{{ $members->total() }} socios registrados.</p>
        </div>
        <div class="d-flex gap-2">
            @can('import-members')
                <a href="{{ route('members.import.show') }}" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-file-import me-1"></i> Importar Excel
                </a>
            @endcan
            <a href="{{ route('members.create') }}" class="btn btn-brand">
                <i class="fa-solid fa-plus me-1"></i> Nuevo socio
            </a>
        </div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    @if (session('import_errors') && count(session('import_errors')))
        <div class="alert alert-warning py-2">
            <strong>Avisos de importación:</strong>
            <ul class="mb-0 small">
                @foreach (array_slice(session('import_errors'), 0, 10) as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Búsqueda / filtro: GET simple, sin JS. --}}
    <form method="GET" class="app-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small mb-1">Buscar</label>
                <input type="text" name="q" value="{{ request('q') }}"
                       class="form-control" placeholder="Nombre, apellido o número de socio">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-brand flex-fill">Filtrar</button>
                @if (request()->hasAny(['q', 'status']))
                    <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                @endif
            </div>
        </div>
    </form>

    <div class="app-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">#</th>
                        <th>Nombre</th>
                        <th>Tipo de socio</th>
                        <th>Estado</th>
                        <th class="text-end">Cuota</th>
                        <th>Próx. pago</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td class="ps-3 mono">{{ $member->socio_number }}</td>
                            <td>
                                <a href="{{ route('members.show', $member) }}" class="text-decoration-none fw-500">
                                    {{ $member->fullName() }}
                                </a>
                            </td>
                            <td class="small">
                                {{ $member->membershipType?->raw_label ?? '—' }}
                            </td>
                            <td><span class="badge-role">{{ $member->status }}</span></td>
                            <td class="text-end mono">
                                {{ $member->fee ? '$' . number_format($member->fee, 0) : '—' }}
                            </td>
                            <td class="small text-muted">
                                {{ $member->next_billing_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('members.edit', $member) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                No se encontraron socios.
                                @if (request()->hasAny(['q', 'status']))
                                    <a href="{{ route('members.index') }}">Ver todos</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $members->links() }}</div>
@endsection
