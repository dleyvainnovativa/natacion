{{-- Modal "agregar socio a la clase": elige un socio del catálogo completo y
     el alcance (solo esta fecha / toda la serie). El JS (SF.openAddMember) fija
     la sesión destino y SF.submitAddMember hace el POST. --}}
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title">Agregar socio a la clase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="am-warnings"></div>

                <div class="mb-3">
                    <label class="form-label small">Socio</label>
                    <input type="text" id="am-filter" class="form-control mb-2" placeholder="Buscar por nombre o número…"
                           oninput="SF.filterAddMember()">
                    <select id="am-member" class="form-select" size="8">
                        @foreach ($allMembers as $m)
                            <option value="{{ $m->id }}"
                                data-text="{{ \Illuminate\Support\Str::lower(($m->socio_number ?? '').' '.($m->first_name ?? '').' '.($m->last_name_1 ?? '')) }}">
                                {{ $m->socio_number ? '#'.$m->socio_number.' · ' : '' }}{{ trim(($m->first_name ?? '').' '.($m->last_name_1 ?? '')) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label small d-block">Aplicar a</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="am-scope" id="am-scope-date" value="date" checked>
                        <label class="btn btn-outline-secondary" for="am-scope-date">Solo esta fecha</label>
                        <input type="radio" class="btn-check" name="am-scope" id="am-scope-series" value="series">
                        <label class="btn btn-outline-secondary" for="am-scope-series">Toda la serie</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-brand" onclick="SF.submitAddMember()">Agregar socio</button>
            </div>
        </div>
    </div>
</div>
