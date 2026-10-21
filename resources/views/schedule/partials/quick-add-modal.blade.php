{{-- Alta rápida de clase desde el calendario (clic en un hueco).
     Variables: $programs, $instructors, $lanes, $members ([id,name,socio]). --}}
<div class="modal fade" id="quickAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('schedule.quick-class.store') }}" class="modal-content app-card" id="quickAddForm">
            @csrf
            <input type="hidden" name="start_date" id="qa-date">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Nueva clase</h5>
                    <div class="small text-muted mono" id="qa-context">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                {{-- Alcance: repetir cada semana vs sólo este día --}}
                <div class="mb-3">
                    <label class="form-label small d-block">Alcance</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="scope" id="qa-scope-weekly" value="weekly" checked>
                        <label class="btn btn-outline-secondary" for="qa-scope-weekly">
                            <i class="fa-solid fa-repeat me-1"></i> Cada semana
                        </label>
                        <input type="radio" class="btn-check" name="scope" id="qa-scope-once" value="once">
                        <label class="btn btn-outline-secondary" for="qa-scope-once">
                            <i class="fa-solid fa-calendar-day me-1"></i> Sólo este día
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Programa *</label>
                    <select name="program_id" id="qa-program" class="form-select" required>
                        @foreach ($programs as $p)
                            <option value="{{ $p->id }}" data-duration="{{ $p->duration_min }}">
                                {{ $p->name }} ({{ $p->duration_min }}′, cupo {{ $p->lane_capacity }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label small">Hora *</label>
                        <input type="time" name="start_time" id="qa-time" class="form-control" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Carril</label>
                        <select name="lane_id" id="qa-lane" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach ($lanes as $lane)
                                <option value="{{ $lane->id }}">{{ $lane->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Instructor</label>
                        <select name="instructor_id" id="qa-instructor" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach ($instructors as $i)
                                <option value="{{ $i->id }}">{{ $i->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label small d-flex justify-content-between">
                        <span>Socios</span>
                        <span class="text-muted" id="qa-roster-count">0</span>
                    </label>
                    <input type="text" class="form-control form-control-sm mb-2"
                           placeholder="Buscar socio…" oninput="SF.filterChecklist(this, '#qa-members')">
                    <div id="qa-members" class="slot-member-list">
                        @foreach ($members as $m)
                            <label class="roster-item d-flex align-items-center gap-2 py-1 px-2 rounded"
                                   data-text="{{ mb_strtolower($m['name'].' '.$m['socio']) }}">
                                <input type="checkbox" name="member_ids[]" value="{{ $m['id'] }}" class="form-check-input mt-0"
                                       onchange="SF.updateChecklistCount('#qa-members', '#qa-roster-count')">
                                <span class="mono small text-muted" style="width:52px">{{ $m['socio'] }}</span>
                                <span class="small">{{ $m['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <small class="text-muted">Opcional. También puedes arrastrar socios o usar el dropdown de la clase.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-brand"><i class="fa-solid fa-plus me-1"></i> Crear clase</button>
            </div>
        </form>
    </div>
</div>
