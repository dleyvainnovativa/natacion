{{-- Modal para marcar un día festivo/inhábil o cierres por mantenimiento.
     Se abre con data-bs-toggle (Bootstrap), sin depender de window.SF: app.js
     reemplaza window.SF al cargar, así que colgar funciones ahí no es confiable. --}}
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
                        <div id="bo-range-list">
                            <div class="d-flex gap-2 align-items-center mb-2 bo-range-row">
                                <input type="time" name="ranges[0][start]" class="form-control form-control-sm" style="width:auto">
                                <span class="text-muted">a</span>
                                <input type="time" name="ranges[0][end]" class="form-control form-control-sm" style="width:auto">
                                <button type="button" class="btn btn-sm btn-outline-danger bo-range-del" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </div>
                        <button type="button" id="bo-add-range" class="btn btn-sm btn-soft">
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
    var list = document.getElementById('bo-range-list');
    var add  = document.getElementById('bo-add-range');
    if (!list || !add) { return; }
    var i = 1;
    function wireDel(row) {
        var b = row.querySelector('.bo-range-del');
        if (b) { b.addEventListener('click', function () { row.remove(); }); }
    }
    Array.prototype.forEach.call(list.querySelectorAll('.bo-range-row'), wireDel);
    add.addEventListener('click', function () {
        var idx = i++;
        var div = document.createElement('div');
        div.className = 'd-flex gap-2 align-items-center mb-2 bo-range-row';
        div.innerHTML =
            '<input type="time" name="ranges[' + idx + '][start]" class="form-control form-control-sm" style="width:auto">' +
            '<span class="text-muted">a</span>' +
            '<input type="time" name="ranges[' + idx + '][end]" class="form-control form-control-sm" style="width:auto">' +
            '<button type="button" class="btn btn-sm btn-outline-danger bo-range-del" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button>';
        list.appendChild(div);
        wireDel(div);
    });
})();
</script>
