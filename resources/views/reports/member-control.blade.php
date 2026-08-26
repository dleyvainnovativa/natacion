@extends('layouts.app')
@section('title', 'Control de socios — Swim Fitness')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="{{ route('reports.index') }}" class="text-decoration-none small text-muted">
                <i class="fa-solid fa-arrow-left me-1"></i> Reportes
            </a>
            <h1 class="h3 mt-2 mb-0">Control de socios</h1>
        </div>
        <a href="{{ route('reports.member-control', ['export' => 'csv']) }}"
           class="btn btn-outline-secondary">
            <i class="fa-solid fa-file-csv me-1"></i> CSV
        </a>
    </div>

    <div class="row g-2 mb-3">
        <div class="col">
            <div class="app-card p-3 text-center">
                <div class="h4 mb-0 text-success">{{ $ok_count }}</div>
                <div class="small text-muted">En regla</div>
            </div>
        </div>
        <div class="col">
            <div class="app-card p-3 text-center">
                <div class="h4 mb-0 text-warning">{{ $under->count() }}</div>
                <div class="small text-muted">Bajo asignados</div>
            </div>
        </div>
        <div class="col">
            <div class="app-card p-3 text-center">
                <div class="h4 mb-0 text-warning">{{ $over->count() }}</div>
                <div class="small text-muted">Sobre asignados</div>
            </div>
        </div>
        <div class="col">
            <div class="app-card p-3 text-center">
                <div class="h4 mb-0 text-muted">{{ $unrated_count }}</div>
                <div class="small text-muted">Sin derecho fijo</div>
            </div>
        </div>
    </div>

    @foreach (['under' => 'Con menos clases de las que les corresponden', 'over' => 'Con más clases de las que les corresponden'] as $key => $title)
        <h2 class="h6 text-muted mt-4 mb-2">{{ $title }}</h2>
        <div class="app-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th class="ps-3">#</th>
                            <th>Socio</th>
                            <th>Tipo</th>
                            <th class="text-end">Derecho</th>
                            <th class="text-end">Asignados</th>
                            <th class="text-end pe-3">Dif.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($$key as $r)
                            <tr>
                                <td class="ps-3 mono">{{ $r['member']->socio_number }}</td>
                                <td>
                                    <a href="{{ route('members.show', $r['member']) }}"
                                       class="text-decoration-none">{{ $r['member']->fullName() }}</a>
                                </td>
                                <td class="small">{{ $r['member']->membershipType?->raw_label }}</td>
                                <td class="text-end mono">{{ $r['entitled'] }}</td>
                                <td class="text-end mono">{{ $r['assigned'] }}</td>
                                <td class="text-end pe-3 mono {{ $r['diff'] < 0 ? 'text-danger' : 'text-warning' }}">
                                    {{ $r['diff'] > 0 ? '+' : '' }}{{ $r['diff'] }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Ninguno.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <p class="text-muted small mt-3">
        "Asignados" cuenta los slots recurrentes donde el socio está en el roster. Los socios con
        tipo especial (beca, acuerdo, pago por clase) o sin días fijos no aparecen aquí.
    </p>
@endsection
