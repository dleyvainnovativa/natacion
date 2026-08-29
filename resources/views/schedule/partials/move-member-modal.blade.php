{{-- Modal "mover socio": elige un socio de la clase origen y una clase destino.
     Los datos ($sessionsPayload) se arman en el controlador y se serializan
     abajo para que el JS (SF.openMoveMember) llene los selects sin otra
     petición. --}}
<div class="modal fade" id="moveMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title">Mover un socio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="mm-warnings"></div>

                <div class="mb-3">
                    <label class="form-label small">Socio a mover</label>
                    <select id="mm-member" class="form-select">
                        <option value="">Cargando…</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Mover a la clase</label>
                    <select id="mm-target" class="form-select">
                        <option value="">Cargando…</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label small d-block">Aplicar a</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="mm-scope" id="mm-scope-date" value="date" checked>
                        <label class="btn btn-outline-secondary" for="mm-scope-date">Solo esta fecha</label>
                        <input type="radio" class="btn-check" name="mm-scope" id="mm-scope-series" value="series">
                        <label class="btn btn-outline-secondary" for="mm-scope-series">Toda la serie</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-brand" onclick="SF.submitMoveMember()">Mover socio</button>
            </div>
        </div>
    </div>
</div>

{{-- El payload viene ya armado del controlador ($sessionsPayload). --}}
<script>
    window.SF_SESSIONS = {{ Illuminate\Support\Js::from($sessionsPayload ?? []) }};
</script>
