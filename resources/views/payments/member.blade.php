@extends('layouts.app')
@section('title', 'Pagos de '.$member->fullName().' — Swim Fitness')
@section('content')

    <div class="mb-4">
        <a href="{{ route('members.show', $member) }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> {{ $member->fullName() }}
        </a>
        <h1 class="h3 mt-2 mb-1">Pagos</h1>
        <p class="text-muted mb-0">
            Socio #{{ $member->socio_number }} ·
            Cuota: {{ $member->fee ? '$'.number_format($member->fee,2) : '—' }} ·
            Próximo pago:
            @if ($member->next_billing_date)
                <span class="{{ $member->next_billing_date->isPast() ? 'text-danger fw-600' : '' }}">
                    {{ $member->next_billing_date->format('d/m/Y') }}
                    @if ($member->next_billing_date->isPast()) (vencido) @endif
                </span>
            @else — @endif
        </p>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 small">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul></div>
    @endif

    <div class="row g-3">
        {{-- Formulario --}}
        <div class="col-md-5">
            <form method="POST" action="{{ route('payments.store', $member) }}" class="app-card p-4">
                @csrf
                <h2 class="h6 mb-3">Registrar pago</h2>

                <div class="mb-3">
                    <label class="form-label small">Tipo</label>
                    <select name="type" class="form-select" id="pay-type"
                            onchange="document.getElementById('pay-concept').value = this.value==='monthly' ? 'Mensualidad' : ''">
                        <option value="monthly">Mensualidad (avanza fecha de pago)</option>
                        <option value="one_off">Otro (inscripción, clase suelta…)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Concepto</label>
                    <input type="text" name="concept" id="pay-concept" class="form-control"
                           value="Mensualidad" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Monto</label>
                    <input type="number" step="0.01" name="amount" class="form-control mono"
                           value="{{ old('amount', $member->fee) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Fecha de pago</label>
                    <input type="date" name="paid_on" class="form-control"
                           value="{{ old('paid_on', now()->format('Y-m-d')) }}" required>
                </div>
                <button class="btn btn-brand w-100">Registrar pago</button>
            </form>
        </div>

        {{-- Historial --}}
        <div class="col-md-7">
            <div class="app-card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th class="ps-3">Fecha</th>
                                <th>Concepto</th>
                                <th>Periodo</th>
                                <th class="text-end pe-3">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $p)
                                <tr>
                                    <td class="ps-3 small mono">{{ $p->paid_on->format('d/m/Y') }}</td>
                                    <td class="small">{{ $p->concept }}</td>
                                    <td class="small text-muted">
                                        @if ($p->period_start)
                                            {{ $p->period_start->format('d/m') }}–{{ $p->period_end?->format('d/m/Y') }}
                                        @else — @endif
                                    </td>
                                    <td class="text-end pe-3 mono">${{ number_format($p->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Sin pagos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
