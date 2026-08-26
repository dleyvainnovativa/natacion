@extends('layouts.app')
@section('title', 'Pago a instructores — Swim Fitness')

@section('content')

    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Reportes
        </a>
        <h1 class="h3 mt-2 mb-0">Pago a instructores</h1>
    </div>

    {{-- Rango de fechas --}}
    <form method="GET" class="app-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-auto">
                <button class="btn btn-brand">Ver</button>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('reports.payroll', array_merge(request()->query(), ['export' => 'csv'])) }}"
                   class="btn btn-outline-secondary">
                    <i class="fa-solid fa-file-csv me-1"></i> CSV
                </a>
            </div>
        </div>
    </form>

    <div class="app-card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">Instructor</th>
                        <th class="text-end">Clases impartidas</th>
                        <th class="text-end">Como suplente</th>
                        <th class="text-end">Pago/clase</th>
                        <th class="text-end pe-3">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="ps-3">{{ $r['instructor']->name }}</td>
                            <td class="text-end mono">{{ $r['classes_taught'] }}</td>
                            <td class="text-end mono">
                                {{ $r['substituted_in'] ?: '—' }}
                            </td>
                            <td class="text-end mono">${{ number_format($r['pay_per_class'], 2) }}</td>
                            <td class="text-end pe-3 mono fw-600">${{ number_format($r['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">
                            No hay clases impartidas en este periodo.
                        </td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="fw-600" style="border-top:2px solid var(--border)">
                            <td class="ps-3">Total del periodo</td>
                            <td class="text-end mono">{{ $rows->sum('classes_taught') }}</td>
                            <td></td><td></td>
                            <td class="text-end pe-3 mono">${{ number_format($total, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="text-muted small">
        Se cuentan solo clases <strong>impartidas</strong>, por el instructor que realmente las dio
        (incluye suplencias). Del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}.
    </p>
@endsection
