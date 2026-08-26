@extends('layouts.app')
@section('title', $member->fullName() . ' — Swim Fitness')
@section('content')

    <div class="mb-4">
        <a href="{{ route('members.index') }}" class="text-decoration-none small text-muted">
            <i class="fa-solid fa-arrow-left me-1"></i> Socios
        </a>
    </div>

    @if (session('ok'))
        <div class="alert alert-success py-2">{{ session('ok') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">{{ $member->fullName() }}</h1>
            <span class="mono text-muted">Socio #{{ $member->socio_number }}</span>
            <span class="badge-role ms-2">{{ $member->status }}</span>
        </div>
        <a href="{{ route('members.edit', $member) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-pen me-1"></i> Editar
        </a>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="app-card p-4 h-100">
                <h2 class="h6 text-muted mb-3">Datos</h2>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Tipo de socio</dt>
                    <dd class="col-7">{{ $member->membershipType?->raw_label ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Cuota</dt>
                    <dd class="col-7 mono">{{ $member->fee ? '$' . number_format($member->fee, 2) : '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Próximo pago</dt>
                    <dd class="col-7">{{ $member->next_billing_date?->format('d/m/Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Teléfono</dt>
                    <dd class="col-7">{{ $member->phone ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Correo</dt>
                    <dd class="col-7">{{ $member->email ?? '—' }}</dd>
                </dl>
                @if ($member->notes)
                    <hr style="border-color:var(--border)">
                    <p class="small mb-0 text-muted">{{ $member->notes }}</p>
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <div class="app-card p-4 h-100">
                <h2 class="h6 text-muted mb-3">Clases asignadas</h2>

                @php
                    $entitled = $member->entitledDaysPerWeek();
                    $assigned = $member->slotAssignments()->count();
                @endphp

                {{-- Flag de control: derecho vs asignado (no bloquea, solo avisa) --}}
                @if ($entitled !== null)
                    @if ($assigned < $entitled)
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Su tipo da derecho a <strong>{{ $entitled }}</strong> días/semana,
                            pero tiene <strong>{{ $assigned }}</strong> asignados.
                        </div>
                    @elseif ($assigned > $entitled)
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Tiene <strong>{{ $assigned }}</strong> clases, más que los
                            <strong>{{ $entitled }}</strong> de su tipo de socio.
                        </div>
                    @else
                        <div class="alert alert-success py-2 small mb-3">
                            <i class="fa-solid fa-check me-1"></i>
                            {{ $assigned }} de {{ $entitled }} días asignados.
                        </div>
                    @endif
                @endif

                @if ($member->sessions->count())
                    <ul class="list-unstyled small mb-0">
                        @foreach ($member->sessions->take(10) as $s)
                            <li class="d-flex justify-content-between py-1">
                                <span>{{ $s->program?->name ?? 'Clase' }}</span>
                                <span class="text-muted mono">{{ $s->starts_at?->format('d/m H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted small mb-0">Sin clases asignadas todavía (se asignan en el horario).</p>
                @endif
            </div>
        </div>
    </div>
@endsection
