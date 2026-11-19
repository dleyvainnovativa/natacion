{{--
    Selector de clases (slots recurrentes vigentes) para inscribir a un socio.
    Variables:
      $slots      Collection<ScheduleSlot> con program/lane/instructor + members_count
      $selected   int[]  ids ya marcados (default [])
      $pickerMode 'create' → oculto hasta elegir tipo de socio (JS sugiere);
                  'manage' → todos visibles (default)

    Filtros en cascada (día → hora → carril): ayudan a encontrar la clase entre
    muchas. Son filtros de VISTA; no cambian lo que se marca.
--}}
@php
    $selected   = $selected   ?? [];
    $pickerMode = $pickerMode ?? 'manage';
    $weekdayShort = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
    $byProgram = $slots->groupBy('program_id');
    $pickerId  = 'slotpick_' . uniqid();
@endphp

<div class="slot-picker-wrap" id="{{ $pickerId }}">
    <div class="slot-filter d-flex flex-wrap gap-2 mb-2">
        <div>
            <label class="form-label small mb-0 text-muted">Día</label>
            <select class="form-select form-select-sm sf-pick-day" style="min-width:120px">
                <option value="">Todos</option>
            </select>
        </div>
        <div>
            <label class="form-label small mb-0 text-muted">Hora</label>
            <select class="form-select form-select-sm sf-pick-time" style="min-width:110px" disabled>
                <option value="">Todas</option>
            </select>
        </div>
        <div>
            <label class="form-label small mb-0 text-muted">Carril</label>
            <select class="form-select form-select-sm sf-pick-lane" style="min-width:140px" disabled>
                <option value="">Todos</option>
            </select>
        </div>
        <div class="align-self-end">
            <button type="button" class="btn btn-sm btn-outline-secondary sf-pick-reset">Limpiar</button>
        </div>
    </div>

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
                               data-weekday="{{ $slot->weekday }}"
                               data-start="{{ $hhmm }}"
                               data-lane="{{ $slot->lane_id }}"
                               data-lane-label="{{ $slot->lane?->label ?? 'Sin carril' }}">
                            <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" {{ $isOn ? 'checked' : '' }}>
                            <span class="slot-day">{{ $weekdayShort[$slot->weekday] ?? '' }}</span>
                            <span class="slot-time mono">{{ $hhmm }}</span>
                            <span class="slot-sub">{{ $slot->lane?->label ?? 'Sin carril' }} · {{ $slot->instructor?->name ?? 'Sin instructor' }}</span>
                            @if ($slot->effective_from && $slot->effective_from->isFuture())
                                <span class="slot-future">desde {{ $slot->effective_from->format('d/m') }}</span>
                            @endif
                            <span class="slot-count {{ $full ? 'is-full' : '' }}">{{ $slot->members_count }}/{{ $cap }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-muted small mb-0">No hay clases vigentes en el horario.</p>
        @endforelse
    </div>
</div>

<script>
(function () {
    const root = document.getElementById(@json($pickerId));
    if (!root) return;
    const dayNames = {1:'Lun',2:'Mar',3:'Mié',4:'Jue',5:'Vie',6:'Sáb',7:'Dom'};
    const daySel  = root.querySelector('.sf-pick-day');
    const timeSel = root.querySelector('.sf-pick-time');
    const laneSel = root.querySelector('.sf-pick-lane');
    const resetBtn = root.querySelector('.sf-pick-reset');
    const chips = Array.from(root.querySelectorAll('.slot-chip'));

    // Opciones de día: sólo los días que existen, en orden.
    const days = [...new Set(chips.map(c => +c.dataset.weekday))].sort((a,b)=>a-b);
    days.forEach(d => {
        const o = document.createElement('option');
        o.value = String(d); o.textContent = dayNames[d] || d;
        daySel.appendChild(o);
    });

    function fillOptions(sel, values, allLabel) {
        const cur = sel.value;
        sel.innerHTML = '';
        const all = document.createElement('option');
        all.value = ''; all.textContent = allLabel;
        sel.appendChild(all);
        values.forEach(v => {
            const o = document.createElement('option');
            o.value = v.value; o.textContent = v.label;
            sel.appendChild(o);
        });
        // conservar selección si sigue disponible
        sel.value = [...sel.options].some(o => o.value === cur) ? cur : '';
    }

    function apply() {
        const day  = daySel.value;
        const time = timeSel.value;
        const lane = laneSel.value;

        // Rellenar HORAS según el día elegido.
        if (day) {
            const times = [...new Set(chips
                .filter(c => c.dataset.weekday === day)
                .map(c => c.dataset.start))].sort();
            fillOptions(timeSel, times.map(t => ({value:t, label:t})), 'Todas');
            timeSel.disabled = false;
        } else {
            fillOptions(timeSel, [], 'Todas');
            timeSel.disabled = true;
        }

        // Rellenar CARRILES según día + hora.
        const t2 = timeSel.value;
        if (day && t2) {
            const seen = new Map();
            chips.filter(c => c.dataset.weekday === day && c.dataset.start === t2)
                 .forEach(c => { if (!seen.has(c.dataset.lane)) seen.set(c.dataset.lane, c.dataset.laneLabel || 'Sin carril'); });
            fillOptions(laneSel, [...seen].map(([value,label]) => ({value, label})), 'Todos');
            laneSel.disabled = false;
        } else {
            fillOptions(laneSel, [], 'Todos');
            laneSel.disabled = true;
        }

        const l2 = laneSel.value;

        // Filtrar chips.
        chips.forEach(c => {
            let show = true;
            if (day && c.dataset.weekday !== day) show = false;
            if (t2  && c.dataset.start   !== t2)  show = false;
            if (l2  && c.dataset.lane    !== l2)  show = false;
            c.style.display = show ? '' : 'none';
        });

        // Ocultar grupos de programa sin chips visibles.
        root.querySelectorAll('.slot-group').forEach(g => {
            if (g.classList.contains('d-none')) return; // modo 'create' oculto por otra razón
            const anyVisible = g.querySelector('.slot-chip:not([style*="display: none"])');
            g.dataset.filteredOut = anyVisible ? '' : '1';
            g.style.display = anyVisible ? '' : 'none';
        });
    }

    daySel.addEventListener('change', () => { timeSel.value=''; laneSel.value=''; apply(); });
    timeSel.addEventListener('change', () => { laneSel.value=''; apply(); });
    laneSel.addEventListener('change', apply);
    resetBtn.addEventListener('click', () => {
        daySel.value=''; timeSel.value=''; laneSel.value=''; apply();
    });
})();
</script>
