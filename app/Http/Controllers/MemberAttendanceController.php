<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\MemberAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberAttendanceController extends Controller
{
    /**
     * Rejilla MENSUAL de asistencia, con el formato que el cliente ya usa a mano:
     * columnas = clases (slot: grupo + horario + instructor), agrupadas por día
     * de la semana; filas = socios del roster; columnas de fecha = cada sesión
     * de ese día en el mes; cada celda es un tick de asistencia.
     */
    public function monthlyGrid(Request $request)
    {
        $this->authorize('mark-member-attendance');

        $monthRef = $this->parseMonth($request->query('month'));
        $monthStart = $monthRef->copy()->startOfMonth();
        $monthEnd   = $monthRef->copy()->endOfMonth();

        // Sesiones del mes (no canceladas) con lo necesario para armar la rejilla.
        $sessions = ClassSession::query()
            ->whereBetween('starts_at', [$monthStart, $monthEnd])
            ->where('status', '!=', 'cancelled')
            ->with([
                'program',
                'lane',
                'actualInstructor',
                'slot',
                'members:id,socio_number,first_name,last_name_1',
                'memberAttendances:id,class_session_id,member_id,status'
            ])
            ->orderBy('starts_at')
            ->get();

        // Agrupar por SLOT (la "columna" del cliente). Sesiones sin slot (movidas
        // manualmente sin plantilla) se agrupan por una clave sintética.
        $groups = [];
        foreach ($sessions as $s) {
            $slotKey = $s->schedule_slot_id ? 'slot-' . $s->schedule_slot_id : 'adhoc-' . $s->id;

            if (! isset($groups[$slotKey])) {
                $groups[$slotKey] = [
                    'title'      => strtoupper($s->program?->name ?? 'Clase'),
                    'lane'       => $s->lane?->label,
                    'time'       => $s->starts_at->format('H:i'),
                    'instructor' => $s->actualInstructor?->name ?? '—',
                    'weekday'    => $s->starts_at->isoWeekday(),
                    'dates'      => [],   // 'Y-m-d' => session_id
                    'roster'     => [],   // member_id => ['socio','name']
                    'marks'      => [],   // member_id => ['Y-m-d' => status]
                    'sessionByDate' => [],
                ];
            }
            $g = &$groups[$slotKey];

            $dateKey = $s->starts_at->toDateString();
            $g['dates'][$dateKey] = $s->id;
            $g['sessionByDate'][$dateKey] = $s->id;

            foreach ($s->members as $m) {
                $g['roster'][$m->id] ??= [
                    'socio' => $m->socio_number,
                    'name'  => trim(($m->first_name ?? '') . ' ' . ($m->last_name_1 ?? '')),
                ];
            }
            $attByMember = $s->memberAttendances->keyBy('member_id');
            foreach ($s->members as $m) {
                $g['marks'][$m->id][$dateKey] = $attByMember->get($m->id)?->status; // present|absent|excused|null
            }
            unset($g);
        }

        // Ordenar fechas dentro de cada grupo y ordenar grupos por día/hora.
        foreach ($groups as &$g) {
            ksort($g['dates']);
        }
        unset($g);
        uasort($groups, fn($a, $b) => [$a['weekday'], $a['time']] <=> [$b['weekday'], $b['time']]);

        $months = \App\Models\ScheduleTemplate::orderByDesc('year')->orderByDesc('month')->get();

        return view('attendance.monthly', [
            'groups'   => $groups,
            'month'    => $monthStart->format('Y-m'),
            'monthLabel' => $this->monthLabel($monthStart),
            'months'   => $months,
            'weekdays' => [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'],
        ]);
    }

    /** Toggle de una celda: cicla vacío → present → absent → excused → vacío. */
    public function toggleCell(ClassSession $session, Request $request)
    {
        $this->authorize('mark-member-attendance');
        $this->guardInstructorOwnsSession($request, $session);

        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'status'    => ['nullable', 'in:present,absent,excused'],
        ]);

        // El socio debe pertenecer a la sesión.
        if (! $session->members()->where('members.id', $data['member_id'])->exists()) {
            abort(422, 'El socio no pertenece a esta clase.');
        }

        if (empty($data['status'])) {
            // Vacío: borrar el registro si existía.
            MemberAttendance::where('class_session_id', $session->id)
                ->where('member_id', $data['member_id'])->delete();
            $newStatus = null;
        } else {
            MemberAttendance::updateOrCreate(
                ['class_session_id' => $session->id, 'member_id' => $data['member_id']],
                ['status' => $data['status'], 'marked_by' => $request->user()->id, 'marked_at' => Carbon::now()],
            );
            $newStatus = $data['status'];

            if ($session->status === 'scheduled') {
                $session->update(['status' => 'held']);
            }
        }

        return response()->json(['ok' => true, 'status' => $newStatus]);
    }

    /** 'YYYY-MM' -> Carbon (día 1); default: mes actual. */
    private function parseMonth(?string $month): Carbon
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        }
        return Carbon::now()->startOfMonth();
    }

    private function monthLabel(Carbon $d): string
    {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        return ($meses[$d->month] ?? $d->month) . ' ' . $d->year;
    }

    /** Lista las sesiones donde el usuario puede pasar lista. Un instructor ve
     * las sesiones donde es el instructor REAL (incluye clases que tomó como
     * suplente y excluye las que cedió). Admin ve todas.
     */
    public function index(Request $request)
    {
        $this->authorize('mark-member-attendance');

        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $query = ClassSession::with(['program', 'lane', 'actualInstructor', 'members'])
            ->whereDate('starts_at', $date->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at');

        // Filtrar por instructor real, salvo admin.
        $user = $request->user();
        if ($user->isRole(Role::Instructor) && $user->instructor) {
            $query->where('actual_instructor_id', $user->instructor->id);
        }

        return view('attendance.members.index', [
            'sessions' => $query->get(),
            'date'     => $date,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
        ]);
    }

    /** Pantalla de pase de lista de una sesión. */
    public function show(ClassSession $session, Request $request)
    {
        $this->authorize('mark-member-attendance');
        $this->guardInstructorOwnsSession($request, $session);

        $session->load(['program', 'members', 'memberAttendances']);

        // Mapa member_id => status ya registrado, para precargar.
        $existing = $session->memberAttendances->keyBy('member_id');

        return view('attendance.members.show', compact('session', 'existing'));
    }

    /** Guarda el pase de lista (todos los socios de la sesión de una vez). */
    public function store(ClassSession $session, Request $request)
    {
        $this->authorize('mark-member-attendance');
        $this->guardInstructorOwnsSession($request, $session);

        $data = $request->validate([
            'attendance'   => ['required', 'array'],
            'attendance.*' => ['in:present,absent,excused'],
        ]);

        $rosterIds = $session->members()->pluck('members.id')->all();

        foreach ($data['attendance'] as $memberId => $status) {
            // Solo socios que están en la sesión.
            if (! in_array((int) $memberId, $rosterIds, true)) {
                continue;
            }

            MemberAttendance::updateOrCreate(
                ['class_session_id' => $session->id, 'member_id' => $memberId],
                [
                    'status'    => $status,
                    'marked_by' => $request->user()->id,
                    'marked_at' => Carbon::now(),
                ]
            );
        }

        // Marcar la sesión como impartida si aún no lo estaba.
        if ($session->status === 'scheduled') {
            $session->update(['status' => 'held']);
        }

        return redirect()->route('attendance.members.index', ['date' => $session->starts_at->toDateString()])
            ->with('ok', 'Asistencia registrada.');
    }

    /**
     * Un instructor solo pasa lista de sus propias sesiones (como instructor
     * real). Admin puede cualquiera.
     */
    private function guardInstructorOwnsSession(Request $request, ClassSession $session): void
    {
        $user = $request->user();

        if (
            $user->isRole(Role::Instructor)
            && $user->instructor
            && $session->actual_instructor_id !== $user->instructor->id
        ) {
            abort(403, 'Esta clase no está asignada a ti.');
        }
    }
}
