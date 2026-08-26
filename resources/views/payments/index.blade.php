@extends('layouts.app')
@section('title', 'Cobros — Swim Fitness')
@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Cobros</h1>
            <p class="text-muted mb-0">Hoy: <span class="mono fw-600">${{ number_format($todayTotal, 2) }}</span></p>
        </div>
        <a href="{{ route('payments.overdue') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> Ver vencidos
        </a>
    </div>

    <form method="GET" class="app-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-auto">
                <button class="btn btn-brand">Filtrar</button>
            </div>
            @if (request()->hasAny(['from','to']))
                <div class="col-auto">
                    <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            @endif
        </div>
    </form>

    <div class="app-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">Fecha</th>
                        <th>Socio</th>
                        <th>Concepto</th>
                        <th>Registró</th>
                        <th class="text-end pe-3">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td class="ps-3 small mono">{{ $p->paid_on->format('d/m/Y') }}</td>
                            <td class="small">
                                <a href="{{ route('payments.member', $p->member) }}" class="text-decoration-none">
                                    {{ $p->member?->fullName() ?? '—' }}
                                </a>
                            </td>
                            <td class="small">{{ $p->concept }}</td>
                            <td class="small text-muted">{{ $p->recorder?->name ?? '—' }}</td>
                            <td class="text-end pe-3 mono">${{ number_format($p->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">Sin cobros en este rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $payments->links() }}</div>
@endsection
