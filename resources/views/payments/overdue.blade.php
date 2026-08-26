@extends('layouts.app')
@section('title', 'Socios vencidos — Swim Fitness')
@section('content')

    <div class="mb-3">
        <a href="{{ route('payments.index') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Cobros
        </a>
        <h1 class="h3 mt-2 mb-1">Socios vencidos</h1>
        <p class="text-muted mb-0">
            {{ $rows->count() }} socios con pago pendiente ·
            estimado por cobrar: <span class="mono fw-600">${{ number_format($total, 2) }}</span>
        </p>
    </div>

    <div class="app-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">#</th>
                        <th>Socio</th>
                        <th>Tipo</th>
                        <th>Venció</th>
                        <th class="text-end">Días</th>
                        <th class="text-end">Meses</th>
                        <th class="text-end pe-3">Estimado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="ps-3 mono">{{ $r['member']->socio_number }}</td>
                            <td>
                                <a href="{{ route('payments.member', $r['member']) }}" class="text-decoration-none">
                                    {{ $r['member']->fullName() }}
                                </a>
                            </td>
                            <td class="small">{{ $r['member']->membershipType?->raw_label ?? '—' }}</td>
                            <td class="small text-danger">{{ $r['due_date']->format('d/m/Y') }}</td>
                            <td class="text-end mono">{{ $r['days_overdue'] }}</td>
                            <td class="text-end mono">{{ $r['months_owed'] }}</td>
                            <td class="text-end pe-3 mono">
                                {{ $r['fee'] ? '$'.number_format($r['fee'] * $r['months_owed'], 2) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            No hay socios vencidos. 🎉
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3">
        "Meses" y "estimado" son aproximados (días vencidos ÷ 30 × cuota). El monto real depende
        de promociones y acuerdos. Registrar una mensualidad avanza la fecha y saca al socio de aquí.
    </p>
@endsection
