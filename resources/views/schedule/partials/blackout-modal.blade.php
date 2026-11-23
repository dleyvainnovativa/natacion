{{-- Modal para marcar un día festivo/inhábil o cierres por mantenimiento. --}}
<div class="modal fade" id="blackoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('blackouts.store') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <div class="modal-header">
                    <h5 class="modal-title">Festivo / inhábil — {{ $date->isoFormat('ddd D [de] MMM') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small mb-1">Motivo</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="type" id="bo-festivo" value="festivo" checked>
                            <label class="btn btn-outline-secondary" for="bo-festivo">Festivo / inhábil</label>
                            <input type="radio" class="btn-check" name="type" id="bo-mant" value="mantenimiento">
                            <label class="btn btn-outline-secondary" for="bo-mant">Mantenimiento</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small mb-1">Alcance</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="scope" id="bo-allday" value="all_day" checked
                                   onchange="document.getElementById('bo-ranges').style.display='none'">
                            <label class="form-check-label" for="bo-allday">Todo el día (no cuenta nada ese día)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="scope" id="bo-rangescope" value="ranges"
                                   onchange="document.getElementById('bo-ranges').style.display=''">
                            <label class="form-check-label" for="bo-rangescope">Solo ciertos horarios (mantenimiento, etc.)</label>
                        </div>
                    </div>

                    <div id="bo-ranges" style="display:none">
                        <label class="form-label small mb-1">Horarios cerrados</label>
                        <div id="bo-range-list"></div>
                        <button type="button" class="btn btn-sm btn-soft" onclick="SF.addBlackoutRange()">
                            <i class="fa-solid fa-plus me-1"></i> Agregar horario
                        </button>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small mb-1">Nota (opcional)</label>
                        <input type="text" name="reason" maxlength="160" class="form-control form-control-sm"
                               placeholder="p. ej. Día festivo oficial, mantenimiento de bomba…">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    let i = 0;
    function rangeRow() {
        const idx = i++;
        const div = document.createElement('div');
        div.className = 'd-flex gap-2 align-items-center mb-2';
        div.innerHTML =
            '<input type="time" name="ranges[' + idx + '][start]" class="form-control form-control-sm" style="width:auto">' +
            '<span class="text-muted">a</span>' +
            '<input type="time" name="ranges[' + idx + '][end]" class="form-control form-control-sm" style="width:auto">' +
            '<button type="button" class="btn btn-sm btn-outline-danger" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button>';
        div.querySelector('button').addEventListener('click', () => div.remove());
        return div;
    }
    window.SF = window.SF || {};
    window.SF.addBlackoutRange = function () {
        document.getElementById('bo-range-list').appendChild(rangeRow());
    };
    window.SF.openBlackoutModal = function () {
        const list = document.getElementById('bo-range-list');
        if (list && !list.children.length) { list.appendChild(rangeRow()); }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('blackoutModal')).show();
    };
})();
</script>
