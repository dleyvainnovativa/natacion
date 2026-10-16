{{-- Bloque de clase en el lienzo. Muestra hora, programa, instructor, conteo y
     la lista de alumnos que quepa (según la altura por duración) + "+N más".
     Variables: $s (ClassSession con program/lane/actualInstructor/members),
     $pxPerMin, $startMin. --}}
@php
    $mins   = $s->starts_at->hour * 60 + $s->starts_at->minute;
    $top    = ($mins - $startMin) * $pxPerMin;
    $dur    = $s->duration_min;
    $h      = max(34, $dur * $pxPerMin);
    $color  = $s->program?->color ?? 'teal';
    $subbed = $s->actual_instructor_id && $s->actual_instructor_id !== $s->scheduled_instructor_id;
    $cap    = $s->program?->lane_capacity ?? 0;
    $count  = $s->members->count();
    $full   = $cap && $count >= $cap;
    $names  = $s->members
        ->map(fn ($m) => trim(($m->first_name ?? '') . ' ' . ($m->last_name_1 ?? '')))
        ->filter()->values();
    // Cuántas líneas de nombre caben: alto del bloque menos la cabecera (~42px), ~12px por línea.
    $maxLines = (int) floor(max(0, $h - 42) / 12);
    if ($names->count() <= $maxLines) {
        $show  = $names;            // caben todos
        $extra = 0;
    } else {
        $show  = $names->take(max(0, $maxLines - 1));  // deja una línea para "+N más"
        $extra = $names->count() - $show->count();
    }
@endphp
<div class="dc-event chip-{{ $color }} {{ $s->status === 'cancelled' ? 'is-cancelled' : '' }}"
     style="top: {{ $top }}px; height: {{ $h }}px"
     data-session-id="{{ $s->id }}"
     data-duration="{{ $dur }}"
     data-starts="{{ $s->starts_at->format('Y-m-d\TH:i') }}"
     data-lane="{{ $s->lane_id }}"
     data-instructor="{{ $s->scheduled_instructor_id }}"
     title="{{ $s->starts_at->format('H:i') }} · {{ $s->program?->name }} · {{ $s->actualInstructor?->name ?? '—' }} · {{ $count }} alumnos">
    <div class="dc-ev-top">
        <span class="dc-event-time mono">{{ $s->starts_at->format('H:i') }}</span>
        <span class="dc-ev-count {{ $full ? 'is-full' : '' }}">{{ $count }}@if ($cap)/{{ $cap }}@endif</span>
    </div>
    <div class="dc-event-title">{{ $s->program?->name ?? 'Clase' }}</div>
    <div class="dc-ev-inst"><i class="fa-solid fa-user"></i> {{ $s->actualInstructor?->name ?? '—' }}@if ($subbed) <span class="badge-role">supl.</span>@endif</div>
    @if ($maxLines > 0 && $names->count())
        <ul class="dc-ev-members">
            @foreach ($show as $n)<li>{{ $n }}</li>@endforeach
            @if ($extra > 0)<li class="more">+{{ $extra }} más</li>@endif
        </ul>
    @endif
</div>
