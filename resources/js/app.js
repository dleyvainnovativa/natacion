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

Object.assign(window.SF, { openMoveMember, submitMoveMember, filterRoster });


Object.assign(window.SF, { initScheduleDnD });
