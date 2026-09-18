@extends('layouts.app')
@section('title', 'Asistencia mensual — Swim Fitness')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
        <div>
            <h1 class="h3 mb-1">Asistencia mensual</h1>
            <p class="text-muted mb-0">Formato por clase y día — {{ $monthLabel }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" id="monthForm" class="d-flex align-items-center gap-2">
                <label class="form-label small mb-0 text-muted">Mes</label>
                <input type="month" name="month" value="{{ $month }}"
                       class="form-control form-control-sm" style="width:auto"
                       onchange="document.getElementById('monthForm').submit()">
            </form>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Imprimir
            </button>
        </div>
    </div>

    <div class="print-title d-none">Asistencia — {{ $monthLabel }}</div>

    @if (session('ok'))
        <div class="alert alert-success no-print">{{ session('ok') }}</div>
    @endif

    <p class="text-muted small no-print">
        Clic en una celda para marcar: vacío → <span class="att-legend att-present">P</span> presente →
        <span class="att-legend att-absent">F</span> falta →
        <span class="att-legend att-excused">J</span> justificada → vacío.
    </p>

    @forelse ($groups as $g)
        <div class="app-card p-0 mb-4 att-group">
            <div class="att-group-head">
                <div class="att-group-title">{{ $g['title'] }}@if($g['lane']) · {{ $g['lane'] }}@endif</div>
                <div class="att-group-sub">
                    <span class="att-time">{{ $g['time'] }}</span>
                    · {{ $weekdays[$g['weekday']] ?? '' }}
                    · {{ $g['instructor'] }}
                </div>
            </div>

            <div style="overflow-x:auto">
                <table class="att-grid">
                    <thead>
                        <tr>
                            <th class="att-socio">#</th>
                            <th class="att-name">Alumno</th>
                            @foreach ($g['dates'] as $date => $sessionId)
                                <th class="att-date">{{ \Illuminate\Support\Carbon::parse($date)->format('d') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($g['roster'] as $memberId => $info)
                            <tr>
                                <td class="att-socio mono">{{ $info['socio'] }}</td>
                                <td class="att-name">{{ $info['name'] }}</td>
                                @foreach ($g['dates'] as $date => $sessionId)
                                    @php $status = $g['marks'][$memberId][$date] ?? null; @endphp
                                    <td class="att-cell {{ $status ? 'att-'.$status : '' }}"
                                        data-session="{{ $sessionId }}"
                                        data-member="{{ $memberId }}"
                                        data-status="{{ $status }}"
                                        role="button" tabindex="0"
                                        onclick="SF.toggleAttendanceCell(this)"
                                        onkeydown="if(event.key===' '||event.key==='Enter'){event.preventDefault();SF.toggleAttendanceCell(this)}">
                                        <span class="att-mark">{{ ['present'=>'P','absent'=>'F','excused'=>'J'][$status] ?? '' }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($g['dates']) + 2 }}" class="text-muted small text-center py-3">Sin alumnos en el roster.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="app-card p-5 text-center text-muted">
            <i class="fa-regular fa-calendar-xmark fs-1 mb-3 d-block" style="color:var(--brand-teal)"></i>
            No hay clases con sesiones este mes. Genera las sesiones o cambia de mes.
        </div>
    @endforelse

@endsection
