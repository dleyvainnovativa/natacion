@extends('layouts.app')
@section('title', 'Reportes — Swim Fitness')

@section('content')
    <h1 class="h3 mb-4">Reportes</h1>

    <div class="row g-3" style="max-width:760px">
        <div class="col-md-6">
            <a href="{{ route('reports.payroll') }}" class="text-decoration-none">
                <div class="app-card p-4 h-100">
                    <i class="fa-solid fa-money-check-dollar fs-3 mb-3" style="color:var(--brand-teal)"></i>
                    <h2 class="h5 mb-1">Pago a instructores</h2>
                    <p class="text-muted small mb-0">
                        Clases impartidas por instructor en un periodo, con suplencias y total a pagar.
                    </p>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="{{ route('reports.member-control') }}" class="text-decoration-none">
                <div class="app-card p-4 h-100">
                    <i class="fa-solid fa-user-gear fs-3 mb-3" style="color:var(--brand-indigo)"></i>
                    <h2 class="h5 mb-1">Control de socios</h2>
                    <p class="text-muted small mb-0">
                        Socios con menos o más clases asignadas de las que da su tipo de socio.
                    </p>
                </div>
            </a>
        </div>
    </div>
@endsection
