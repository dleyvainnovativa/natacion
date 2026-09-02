/**
 * Swim Fitness — helpers JS centralizados.
 * Vanilla JS. Importar funciones donde se necesiten en vez de repetir fetch.
 */

import './bootstrap-theme';

const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const baseHeaders = () => ({
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrf(),
    'X-Requested-With': 'XMLHttpRequest',
});

/** Núcleo de peticiones. Lanza en respuestas no OK con el body parseado. */
async function request(url, method, body = null) {
    const opts = { method, headers: baseHeaders() };
    if (body !== null) opts.body = JSON.stringify(body);

    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        const err = new Error(data.message || 'Error en la solicitud');
        err.status = res.status;
        err.data = data;
        throw err;
    }
    return data;
}

export const http = {
    get:    (url)        => request(url, 'GET'),
    post:   (url, body)  => request(url, 'POST', body),
    put:    (url, body)  => request(url, 'PUT', body),
    delete: (url)        => request(url, 'DELETE'),
};

/** Notificaciones tipo toast (contenedor #toast-root en el layout). */
export function toast(message, type = 'success') {
    const root = document.getElementById('toast-root');
    if (!root) return;

    const el = document.createElement('div');
    el.className = `toast-item toast-${type}`;
    el.setAttribute('role', 'status');
    el.textContent = message;
    root.appendChild(el);

    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 250);
    }, 3200);
}

/** Estado de carga en un botón (deshabilita + spinner). */
export function setLoading(btn, loading, labelWhenDone) {
    if (!btn) return;
    if (loading) {
        btn.dataset.originalLabel = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Cargando…';
    } else {
        btn.disabled = false;
        btn.innerHTML = labelWhenDone ?? btn.dataset.originalLabel ?? btn.innerHTML;
    }
}

/** Serializa un <form> a objeto plano (sin usar <form> submit nativo). */
export function serializeForm(form) {
    const out = {};
    new FormData(form).forEach((value, key) => {
        if (key.endsWith('[]')) {
            const k = key.slice(0, -2);
            (out[k] ??= []).push(value);
        } else {
            out[key] = value;
        }
    });
    return out;
}

/** Helper de modal Bootstrap por id. */
export const modal = {
    show(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show(); },
    hide(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).hide(); },
};

// Exponer en window para scripts inline puntuales de Blade.
window.SF = { http, toast, setLoading, serializeForm, modal };

/* ==================================================================
 * TIER 2 — Horario. Pegar al final de resources/js/app.js
 * (usa http, toast, modal ya definidos arriba en este archivo)
 * ================================================================== */

let activeSessionId = null;

/** Abre el modal de mover con los datos de la tarjeta clickeada. */
function openMove(cardEl) {
    activeSessionId = cardEl.dataset.sessionId;

    const startsInput = document.getElementById('move-starts');
    startsInput.value = cardEl.dataset.starts || '';

    document.getElementById('move-lane').value = cardEl.dataset.lane || '';
    document.getElementById('move-instructor').value = cardEl.dataset.instructor || '';
    document.getElementById('move-notes').value = '';
    document.getElementById('move-warnings').innerHTML = '';
    document.getElementById('scope-date').checked = true;

    // Previsualizar conflictos cuando cambian fecha/carril/instructor.
    ['move-starts', 'move-lane', 'move-instructor'].forEach((id) => {
        document.getElementById(id).onchange = previewConflicts;
    });

    modal.show('moveModal');
}

async function previewConflicts() {
    if (!activeSessionId) return;
    const payload = collectMovePayload();
    try {
        const res = await http.post(`/horario/sesiones/${activeSessionId}/conflictos`, payload);
        renderWarnings(res.warnings || []);
    } catch (e) {
        // Silencioso: la previsualización no debe estorbar.
    }
}

function collectMovePayload() {
    return {
        starts_at: document.getElementById('move-starts').value,
        lane_id: document.getElementById('move-lane').value || null,
        instructor_id: document.getElementById('move-instructor').value || null,
    };
}

function renderWarnings(warnings) {
    const box = document.getElementById('move-warnings');
    if (!warnings.length) { box.innerHTML = ''; return; }
    box.innerHTML =
        '<div class="alert alert-warning py-2 small"><strong>Avisos:</strong><ul class="mb-0">' +
        warnings.map((w) => `<li>${w}</li>`).join('') +
        '</ul></div>';
}

async function submitMove() {
    if (!activeSessionId) return;
    const scope = document.querySelector('input[name="move-scope"]:checked')?.value || 'date';
    const payload = {
        ...collectMovePayload(),
        scope,
        notes: document.getElementById('move-notes').value || null,
    };
    try {
        const res = await http.post(`/horario/sesiones/${activeSessionId}/mover`, payload);
        renderWarnings(res.warnings || []);
        toast(res.message || 'Clase movida.');
        setTimeout(() => location.reload(), 700);
    } catch (e) {
        toast(e.data?.message || 'No se pudo mover la clase.', 'error');
    }
}

async function cancelSession() {
    if (!activeSessionId) return;
    if (!confirm('¿Cancelar esta clase?')) return;
    const notes = document.getElementById('move-notes').value || null;
    try {
        const res = await http.post(`/horario/sesiones/${activeSessionId}/cancelar`, { notes });
        toast(res.message || 'Clase cancelada.');
        setTimeout(() => location.reload(), 700);
    } catch (e) {
        toast('No se pudo cancelar.', 'error');
    }
}

/** Filtro de lista simple (roster). */
function filterList(input, itemSelector) {
    const term = input.value.trim().toLowerCase();
    document.querySelectorAll(itemSelector).forEach((el) => {
        const text = el.dataset.text || el.textContent.toLowerCase();
        el.style.display = text.includes(term) ? '' : 'none';
    });
}

// Exponer en el objeto global SF (definido arriba en app.js).
Object.assign(window.SF, {
    openMove, submitMove, cancelSession, filterList,
});

/* ==================================================================
 * TIER 3 — Asistencia. Pegar al final de resources/js/app.js
 * ================================================================== */

/**
 * Guarda la asistencia de un instructor desde la fila de la tabla. Lee estado
 * y suplente de los selects de la misma fila, hace POST y refleja el resultado
 * (quién impartió) sin recargar.
 */
async function saveInstructorAttendance(btn) {
    const row = btn.closest('tr[data-session]');
    const sessionId = row.dataset.session;
    const status = row.querySelector('.att-status').value;
    const substitute = row.querySelector('.att-sub').value || null;

    // Regla de UI: si es "puntual" no tiene sentido un suplente.
    const payload = {
        status,
        substitute_id: status === 'on_time' ? null : substitute,
    };

    SF.setLoading(btn, true);
    try {
        const res = await SF.http.post(`/asistencia/instructores/${sessionId}`, payload);
        row.querySelector('.att-actual').textContent = res.actual_instructor || '—';

        if (res.warnings && res.warnings.length) {
            res.warnings.forEach((w) => SF.toast(w, 'error'));
        }
        SF.toast(res.message || 'Guardado.');
    } catch (e) {
        SF.toast(e.data?.message || 'No se pudo guardar.', 'error');
    } finally {
        SF.setLoading(btn, false, 'Guardar');
    }
}

Object.assign(window.SF, { saveInstructorAttendance });


function initScheduleDnD() {
    // Solo desktop: si el ancho es de móvil, no activar DnD.
    if (window.matchMedia('(max-width: 991.98px)').matches) return;

    const cards = document.querySelectorAll('.session-card[data-session-id]');
    const columns = document.querySelectorAll('.schedule-col[data-date]');
    if (!cards.length || !columns.length) return;

    let draggedCard = null;

    cards.forEach((card) => {
        card.setAttribute('draggable', 'true');

        card.addEventListener('dragstart', (e) => {
            draggedCard = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            // Necesario en Firefox para iniciar el drag.
            e.dataTransfer.setData('text/plain', card.dataset.sessionId);
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            columns.forEach((c) => c.classList.remove('drop-target'));
            draggedCard = null;
        });
    });

    columns.forEach((col) => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault(); // permite soltar
            e.dataTransfer.dropEffect = 'move';
            col.classList.add('drop-target');
        });

        col.addEventListener('dragleave', () => col.classList.remove('drop-target'));

        col.addEventListener('drop', (e) => {
            e.preventDefault();
            col.classList.remove('drop-target');
            if (!draggedCard) return;

            const targetDate = col.dataset.date;           // Y-m-d
            const originalStarts = draggedCard.dataset.starts; // Y-m-dTH:i
            const originalTime = originalStarts.includes('T')
                ? originalStarts.split('T')[1]
                : '00:00';

            // Si soltó en el mismo día, no hacer nada.
            const originalDate = originalStarts.split('T')[0];
            if (originalDate === targetDate) return;

            // Abrir el modal existente precargado con el nuevo día + hora original.
            SF.openMove(draggedCard);
            const startsInput = document.getElementById('move-starts');
            if (startsInput) {
                startsInput.value = `${targetDate}T${originalTime}`;
                // Disparar la previsualización de conflictos.
                startsInput.dispatchEvent(new Event('change'));
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initScheduleDnD);


/* ==================================================================
 * BUNDLE B — Mover socio individual + fix del filtro de roster.
 * Pegar al final de resources/js/app.js.
 * ================================================================== */

let mmSourceSessionId = null;

/** Abre el modal "mover socio" con los socios de la clase origen. */
function openMoveMember(sessionId) {
    mmSourceSessionId = sessionId;
    const sessions = window.SF_SESSIONS || [];
    const source = sessions.find((s) => s.id === sessionId);

    const memberSel = document.getElementById('mm-member');
    const targetSel = document.getElementById('mm-target');
    document.getElementById('mm-warnings').innerHTML = '';

    // Socios de la clase origen.
    memberSel.innerHTML = '';
    if (!source || !source.members.length) {
        memberSel.innerHTML = '<option value="">(esta clase no tiene socios)</option>';
    } else {
        source.members.forEach((m) => {
            memberSel.insertAdjacentHTML('beforeend',
                `<option value="${m.id}">${m.name}</option>`);
        });
    }

    // Clases destino (todas menos la origen).
    targetSel.innerHTML = '';
    sessions.filter((s) => s.id !== sessionId).forEach((s) => {
        targetSel.insertAdjacentHTML('beforeend',
            `<option value="${s.id}">${s.label}</option>`);
    });

    document.getElementById('mm-scope-date').checked = true;
    SF.modal.show('moveMemberModal');
}

async function submitMoveMember() {
    const memberId = document.getElementById('mm-member').value;
    const toSession = document.getElementById('mm-target').value;
    const scope = document.querySelector('input[name="mm-scope"]:checked')?.value || 'date';

    if (!memberId || !toSession) {
        SF.toast('Elige socio y clase destino.', 'error');
        return;
    }

    try {
        const res = await SF.http.post(`/horario/sesiones/${mmSourceSessionId}/socio`, {
            member_id: memberId,
            to_session: toSession,
            scope,
        });
        SF.toast(res.message || 'Socio movido.');
        setTimeout(() => location.reload(), 700);
    } catch (e) {
        SF.toast(e.data?.message || 'No se pudo mover al socio.', 'error');
    }
}

/**
 * Filtro de roster — versión robusta e independiente de SF.
 * Se llama con oninput. Compara contra data-text (o el texto visible).
 */
function filterRoster(input) {
    const term = (input.value || '').trim().toLowerCase();
    const list = input.closest('form')?.querySelector('#roster-list')
              || document.getElementById('roster-list');
    if (!list) return;
    list.querySelectorAll('.roster-item').forEach((el) => {
        const text = (el.getAttribute('data-text') || el.textContent || '').toLowerCase();
        el.style.display = text.includes(term) ? '' : 'none';
    });
}

/* ==================================================================
 * DÍA — motor de arrastre del lienzo (estilo Google Calendar).
 * Pegar al final de resources/js/app.js.
 *
 * Usa arrastre por puntero (no HTML5 DnD) para posicionar libre. Al soltar,
 * calcula carril + hora (snap 10 min) y confirma con el endpoint de mover que
 * ya existe (schedule.sessions.move), scope 'date'. La duración no cambia.
 * ================================================================== */

const DC_SNAP_MIN = 10;

function initDayCanvas() {
    // Ambas vistas comparten el mismo motor de arrastre. La diferencia: en la
    // vista de día la fecha es única; en la semanal cada carril lleva su propia
    // fecha (data-date en .dc-lane-body). Por eso la fecha se lee del carril
    // destino al soltar, no del lienzo.
    const canvas = document.querySelector('.day-canvas, .week-canvas');
    if (!canvas) return;

    const pxPerMin = parseFloat(getComputedStyle(canvas).getPropertyValue('--px-per-min')) || 1.2;
    const startMin = parseInt(canvas.dataset.startMin, 10);
    const endMin   = parseInt(canvas.dataset.endMin, 10);

    const events = canvas.querySelectorAll('.dc-event[data-session-id]');
    const laneBodies = canvas.querySelectorAll('.dc-lane-body');

    events.forEach((ev) => makeDraggable(ev, { canvas, pxPerMin, startMin, endMin, laneBodies }));
}

function makeDraggable(ev, ctx) {
    let dragging = false;
    let ghost = null;          // clon que sigue el puntero
    let placeholder = null;    // bloque fantasma "snapped" dentro del carril destino
    let badge = null;          // etiqueta flotante con la hora destino
    let offsetY = 0;           // desfase puntero→borde superior del evento
    let hDrag = 0;             // altura del evento (fija por duración)

    // Estado del destino calculado en el último pointermove.
    let target = { lane: null, laneId: null, snapMin: null, date: null };

    ev.addEventListener('pointerdown', (e) => {
        // Solo botón principal; ignora clicks en botones internos si los hubiera.
        if (e.button !== 0) return;
        e.preventDefault();
        dragging = true;
        ev.setPointerCapture(e.pointerId);

        const rect = ev.getBoundingClientRect();
        offsetY = e.clientY - rect.top;
        hDrag = rect.height;

        // Ghost: clon translúcido que sigue el puntero.
        ghost = ev.cloneNode(true);
        ghost.classList.add('dc-ghost');
        ghost.style.height = hDrag + 'px';
        ev.classList.add('dc-dragging-origin');
        document.body.appendChild(ghost);

        // Placeholder: bloque "snapped" que se posiciona en el carril destino.
        placeholder = document.createElement('div');
        placeholder.className = 'dc-placeholder';
        placeholder.style.height = hDrag + 'px';

        // Badge flotante con la hora destino (estilo Google Calendar).
        badge = document.createElement('div');
        badge.className = 'dc-time-badge';
        document.body.appendChild(badge);

        update(e.clientX, e.clientY);
    });

    ev.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        update(e.clientX, e.clientY);
    });

    ev.addEventListener('pointerup', async (e) => {
        if (!dragging) return;
        dragging = false;

        const hadTarget = target.lane && target.snapMin !== null && target.date;
        const laneId = target.laneId;
        const snapMin = target.snapMin;
        const date = target.date;
        cleanup();

        if (!hadTarget) return; // soltó fuera de un carril

        const hh = String(Math.floor(snapMin / 60)).padStart(2, '0');
        const mm = String(snapMin % 60).padStart(2, '0');
        const startsAt = `${date}T${hh}:${mm}`;

        await commitMove(ev.dataset.sessionId, startsAt, laneId);
    });

    ev.addEventListener('pointercancel', () => { if (dragging) { dragging = false; cleanup(); } });

    /* Recalcula carril + hora snapped y actualiza ghost, placeholder y badge. */
    function update(x, y) {
        // Ghost sigue el puntero.
        ghost.style.left = (x - ghost.offsetWidth / 2) + 'px';
        ghost.style.top  = (y - offsetY) + 'px';

        // ¿Sobre qué carril está el borde SUPERIOR del bloque arrastrado?
        const topY = y - offsetY;
        const lane = laneUnder(x, topY + 1, ctx.laneBodies) || laneUnder(x, y, ctx.laneBodies);

        ctx.laneBodies.forEach((lb) => lb.classList.remove('dc-lane-hot'));

        if (!lane) {
            target = { lane: null, laneId: null, snapMin: null, date: null };
            if (placeholder.parentNode) placeholder.remove();
            badge.style.display = 'none';
            return;
        }

        lane.classList.add('dc-lane-hot');

        // Minuto crudo por la posición del borde superior dentro del carril.
        const rect = lane.getBoundingClientRect();
        const relY = topY - rect.top;
        let rawMin = ctx.startMin + (relY / ctx.pxPerMin);
        let snapped = Math.round(rawMin / DC_SNAP_MIN) * DC_SNAP_MIN;

        // No dejar que el bloque se salga del lienzo por arriba/abajo.
        const durMin = hDrag / ctx.pxPerMin;
        snapped = Math.max(ctx.startMin, Math.min(snapped, ctx.endMin - durMin));

        target = {
            lane,
            laneId: lane.dataset.laneId || null,
            snapMin: snapped,
            date: lane.dataset.date || null,
        };

        // Colocar el placeholder dentro del carril, en la posición snapped.
        const topPx = (snapped - ctx.startMin) * ctx.pxPerMin;
        placeholder.style.top = topPx + 'px';
        if (placeholder.parentNode !== lane) lane.appendChild(placeholder);

        // Badge con la hora destino, junto al placeholder. Si el destino cae en
        // otro día (vista semanal), anteponemos el día abreviado.
        const hh = String(Math.floor(snapped / 60)).padStart(2, '0');
        const mm = String(snapped % 60).padStart(2, '0');
        const originDate = (ev.dataset.starts || '').slice(0, 10);
        let label = `${hh}:${mm}`;
        if (target.date && target.date !== originDate) {
            label = `${dowLabel(target.date)} ${hh}:${mm}`;
        }
        badge.textContent = label;
        badge.style.display = 'block';
        badge.style.left = (rect.left + 6) + 'px';
        badge.style.top  = (rect.top + topPx - 10) + 'px';
    }

    function cleanup() {
        if (ghost) { ghost.remove(); ghost = null; }
        if (placeholder && placeholder.parentNode) placeholder.remove();
        placeholder = null;
        if (badge) { badge.remove(); badge = null; }
        ev.classList.remove('dc-dragging-origin');
        ctx.laneBodies.forEach((lb) => lb.classList.remove('dc-lane-hot'));
    }
}

/** Etiqueta de día abreviada (es) a partir de 'YYYY-MM-DD'. */
function dowLabel(isoDate) {
    const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const d = new Date(isoDate + 'T00:00:00');
    return dias[d.getDay()] || '';
}

/** ¿Qué cuerpo de carril está bajo estas coordenadas? */
function laneUnder(x, y, laneBodies) {
    for (const lb of laneBodies) {
        const r = lb.getBoundingClientRect();
        if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) return lb;
    }
    return null;
}

/** Confirma el movimiento con el endpoint existente (scope date). */
async function commitMove(sessionId, startsAt, laneId) {
    try {
        const res = await SF.http.post(`/horario/sesiones/${sessionId}/mover`, {
            scope: 'date',
            starts_at: startsAt,
            lane_id: laneId,
        });
        if (res.warnings && res.warnings.length) {
            res.warnings.forEach((w) => SF.toast(w, 'error'));
        }
        SF.toast(res.message || 'Clase movida.');
        setTimeout(() => location.reload(), 650);
    } catch (e) {
        SF.toast(e.data?.message || 'No se pudo mover la clase.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', initDayCanvas);
Object.assign(window.SF, { initDayCanvas });


Object.assign(window.SF, { openMoveMember, submitMoveMember, filterRoster });


Object.assign(window.SF, { initScheduleDnD });
