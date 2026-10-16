{{--
    Selector de clases (slots recurrentes del mes actual) para inscribir a un
    socio. Variables:
      $slots      Collection<ScheduleSlot> con program/lane/instructor + members_count
      $selected   int[]  ids ya marcados (default [])
      $pickerMode 'create' → oculto hasta elegir tipo de socio (JS sugiere);
                  'manage' → todos visibles (default)
--}}
@php
    $selected   = $selected   ?? [];
    $pickerMode = $pickerMode ?? 'manage';
    $weekdayShort = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
    $byProgram = $slots->groupBy('program_id');
@endphp

<div class="slot-picker" data-mode="{{ $pickerMode }}">
    @forelse ($byProgram as $programId => $group)
        @php $prog = $group->first()->program; @endphp
        <div class="slot-group {{ $pickerMode === 'create' ? 'd-none' : '' }}" data-program-id="{{ $programId }}">
            <div class="slot-group-head">
                <span class="chip-{{ $prog?->color ?? 'teal' }} slot-prog-chip">{{ $prog?->name ?? 'Programa' }}</span>
                <span class="text-muted small">cupo por carril: {{ $prog?->lane_capacity }}</span>
            </div>
            <div class="slot-grid">
                @foreach ($group->sortBy([['weekday', 'asc'], ['start_time', 'asc']]) as $slot)
                    @php
                        $cap  = $prog?->lane_capacity ?? 99;
                        $full = $slot->members_count >= $cap;
                        $hhmm = \Illuminate\Support\Str::of((string) $slot->start_time)->substr(0, 5);
                        $isOn = in_array($slot->id, $selected);
                    @endphp
                    <label class="slot-chip {{ $isOn ? 'is-checked' : '' }}"
                           data-weekday="{{ $slot->weekday }}" data-start="{{ $slot->start_time }}">
                        <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" {{ $isOn ? 'checked' : '' }}>
                        <span class="slot-day">{{ $weekdayShort[$slot->weekday] ?? '' }}</span>
                        <span class="slot-time mono">{{ $hhmm }}</span>
                        <span class="slot-sub">{{ $slot->lane?->label ?? 'Sin carril' }} · {{ $slot->instructor?->name ?? 'Sin instructor' }}</span>
                        <span class="slot-count {{ $full ? 'is-full' : '' }}">{{ $slot->members_count }}/{{ $cap }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-muted small mb-0">No hay clases en la plantilla de este mes.</p>
    @endforelse
</div>
