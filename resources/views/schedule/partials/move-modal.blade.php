{{-- Modal para mover/cancelar una sesión. Lo controla SF.openMove() en app.js --}}
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content app-card">
            <div class="modal-header">
                <h5 class="modal-title">Mover clase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="move-warnings"></div>

                <div class="mb-3">
                    <label class="form-label small">Nueva fecha y hora</label>
                    <input type="datetime-local" id="move-starts" class="form-control">
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Carril</label>
                        <select id="move-lane" class="form-select">
                            <option value="">Sin cambio</option>
                            @foreach (\App\Models\Lane::with('pool')->orderBy('position')->get() as $lane)
                                <option value="{{ $lane->id }}">{{ $lane->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Instructor</label>
                        <select id="move-instructor" class="form-select">
                            <option value="">Sin cambio</option>
                            @foreach (\App\Models\Instructor::where('active', true)->orderBy('name')->get() as $i)
                                <option value="{{ $i->id }}">{{ $i->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label small">Nota (opcional)</label>
                    <input type="text" id="move-notes" class="form-control" placeholder="Motivo del cambio">
                </div>

                <div class="mb-2">
                    <label class="form-label small d-block">Aplicar a</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="move-scope" id="scope-date" value="date" checked>
                        <label class="btn btn-outline-secondary" for="scope-date">Solo esta fecha</label>

                        <input type="radio" class="btn-check" name="move-scope" id="scope-series" value="series">
                        <label class="btn btn-outline-secondary" for="scope-series">Toda la serie</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" onclick="SF.cancelSession()">
                    Cancelar clase
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-brand" onclick="SF.submitMove()">Guardar</button>
            </div>
        </div>
    </div>
</div>
