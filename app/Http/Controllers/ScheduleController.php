<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Models\Member;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    /**
     * Socios para el panel lateral del calendario, con cuántas clases tienen en
     * la semana de $weekStart (para arrastrarlos a una clase e inscribirlos).
     */
    private function panelMembers(Carbon $weekStart): array
    {
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $counts = DB::table('session_members')
            ->join('class_sessions', 'class_sessions.id', '=', 'session_members.class_session_id')
            ->whereBetween('class_sessions.starts_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
            ->where('class_sessions.status', '!=', 'cancelled')
            ->select('session_members.member_id', DB::raw('count(*) as c'))
            ->groupBy('session_members.member_id')
            ->pluck('c', 'session_members.member_id');

        return Member::orderBy('last_name_1')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name_1', 'last_name_2', 'socio_number'])
            ->map(fn ($m) => [
                'id'    => $m->id,
                'name'  => trim("$m->first_name $m->last_name_1"),
                'socio' => $m->socio_number,
                'count' => (int) ($counts[$m->id] ?? 0),
            ])->values()->all();
    }

    /**
     * Vista de SEMANA (escaneo): rejilla horas × días. Es la que ya existía;
     * se conserva como vista general. El drag fino vive en la vista de DÍA.
     */
    public function index(Request $request, SessionGenerator $generator)
    {
        $reference = $request->filled('week')
            ? Carbon::parse($request->week)
            : Carbon::now();

        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY);

        // Patrón de días a mostrar (petición del cliente):
        //   lmv    → Lunes/Miércoles/Viernes (programas de 3 días)
        //   mj     → Martes/Jueves          (programas de 2 días)
        //   semana → Lunes a Viernes (sin fin de semana)
        $pattern = in_array($request->query('pattern'), ['lmv', 'mj'], true)
            ? $request->query('pattern')
            : 'semana';
        $visibleDays = match ($pattern) {
            'lmv'   => [1, 3, 5],
            'mj'    => [2, 4],
            default => [1, 2, 3, 4, 5],
        };

        $this->ensureWeekGenerated($reference, $generator);

        $sessions = $this->filteredSessions($request)
            ->forWeek($reference)
            ->where('status', '!=', 'cancelled')
            ->with([
                'program',
                'lane',
                'actualInstructor',
                'scheduledInstructor',
                'members:id,socio_number,first_name,last_name_1'
            ])
            ->orderBy('starts_at')
            ->get();

        // Mismos límites de lienzo que la vista de día, pero sobre TODA la semana:
        // ventana fija de jornada (config) con expansión si algo cae fuera.
        [$startMin, $endMin] = $this->canvasBounds($sessions);

        // Carriles a mostrar: si hay filtro de carril, solo ese; si no, todos.
        $lanes = Lane::orderBy('position')
            ->when($request->filled('lane'), fn($q) => $q->where('id', $request->integer('lane')))
            ->get();

        // byDay[iso][laneId] = [sesiones]. laneId 0 = sin carril.
        $byDay = [];
        foreach ($sessions as $s) {
            $byDay[$s->starts_at->isoWeekday()][$s->lane_id ?? 0][] = $s;
        }

        // ¿Hay alguna clase sin carril en la semana? (para decidir si mostramos
        // la columna "sin carril").
        $hasUnassigned = $sessions->contains(fn($s) => $s->lane_id === null);

        return view('schedule.index', [
            'weekStart'     => $weekStart,
            'byDay'         => $byDay,
            'lanes'         => $lanes,
            'hasUnassigned' => $hasUnassigned,
            'hasSessions'   => $sessions->isNotEmpty(),
            'startMin'      => $startMin,
            'endMin'        => $endMin,
            'prevWeek'      => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek'      => $weekStart->copy()->addWeek()->toDateString(),
            'weekdays'      => collect($this->weekdayLabels())->only($visibleDays)->all(),
            'pattern'       => $pattern,
            'instructors'   => Instructor::where('active', true)->orderBy('name')->get(),
            'programs'      => Program::where('active', true)->orderBy('name')->get(),
            'allLanes'      => Lane::orderBy('position')->get(), // para el filtro
            'filters'       => $request->only(['instructor', 'program', 'lane']),
            'sessionsPayload' => $this->sessionsPayload($sessions),
            'panelMembers'  => $this->panelMembers($weekStart),
        ]);
    }

    /**
     * Vista de DÍA (edición): un día, una columna por carril, layout por altura
     * de tiempo. Aquí se arrastra libre a cualquier carril/hora (snap 10 min).
     */
    public function day(Request $request, SessionGenerator $generator)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $this->ensureWeekGenerated($date, $generator);

        $lanes = Lane::orderBy('position')->get();

        $sessions = $this->filteredSessions($request)
            ->whereDate('starts_at', $date->toDateString())
            ->where('status', '!=', 'cancelled')
            ->with([
                'program',
                'lane',
                'actualInstructor',
                'scheduledInstructor',
                'members:id,socio_number,first_name,last_name_1'
            ])
            ->orderBy('starts_at')
            ->get();

        // Auto-ajuste de límites del lienzo: 30 min antes de la primera clase y
        // 30 min después del fin de la última. Con margen si no hay clases.
        [$startMin, $endMin] = $this->canvasBounds($sessions);

        // Agrupar por carril; las sesiones sin carril van a una columna "sin carril".
        $byLane = [];
        foreach ($sessions as $s) {
            $byLane[$s->lane_id ?? 0][] = $s;
        }

        return view('schedule.day', [
            'date'        => $date,
            'lanes'       => $lanes,
            'byLane'      => $byLane,
            'startMin'    => $startMin,
            'endMin'      => $endMin,
            'prevDay'     => $date->copy()->subDay()->toDateString(),
            'nextDay'     => $date->copy()->addDay()->toDateString(),
            'instructors' => Instructor::where('active', true)->orderBy('name')->get(),
            'programs'    => Program::where('active', true)->orderBy('name')->get(),
            'filters'     => $request->only(['instructor', 'program']),
            'sessionsPayload' => $this->sessionsPayload($sessions),
            'panelMembers' => $this->panelMembers($date->copy()->startOfWeek(Carbon::MONDAY)),
        ]);
    }

    public function template(Request $request)
    {
        $this->authorize('move-classes');

        // Mes seleccionado (YYYY-MM); por defecto el mes actual.
        $monthRef = $this->parseMonth($request->query('month'));

        // Resuelve (crea/clona) la plantilla del mes elegido.
        $template = \App\Models\ScheduleTemplate::resolveFor($monthRef);

        $slots = ScheduleSlot::with(['program', 'lane', 'instructor', 'members:id'])
            ->where('schedule_template_id', $template->id)
            ->where('active', true)
            ->orderBy('weekday')->orderBy('start_time')
            ->get()->groupBy('weekday');

        // Lista de meses existentes para el selector (más reciente primero).
        $months = \App\Models\ScheduleTemplate::orderByDesc('year')->orderByDesc('month')->get();

        return view('schedule.template', [
            'template'    => $template,
            'month'       => $template->key_month,   // 'YYYY-MM'
            'months'      => $months,
            'slots'       => $slots,
            'weekdays'    => $this->weekdayLabels(),
            'programs'    => Program::where('active', true)->orderBy('name')->get(),
            'instructors' => Instructor::where('active', true)->orderBy('name')->get(),
            'lanes'       => Lane::with('pool')->orderBy('pool_id')->orderBy('position')->get(),
            'members'     => Member::orderBy('last_name_1')->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name_1', 'last_name_2', 'socio_number']),
        ]);
    }

    /** Convierte 'YYYY-MM' a un Carbon en el día 1 de ese mes; default: hoy. */
    private function parseMonth(?string $month): Carbon
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        }
        return Carbon::now()->startOfMonth();
    }

    /**
     * Payload para el modal "mover socio": lista de clases con sus socios, para
     * poblar los selects sin otra petición. IDs como string para casar con los
     * data-session-id del DOM.
     */
    private function sessionsPayload($sessions): array
    {
        return $sessions->map(function ($s) {
            $label = $s->starts_at->format('D H:i') . ' · ' . ($s->program?->name ?? 'Clase')
                . ' · ' . ($s->lane?->label ?? 'Sin carril');
            return [
                'id'      => (string) $s->id,
                'label'   => $label,
                'members' => $s->members
                    ->sortBy(fn($m) => mb_strtolower(trim(($m->last_name_1 ?? '') . ' ' . ($m->first_name ?? ''))))
                    ->map(fn($m) => [
                        'id'    => (string) $m->id,
                        'socio' => $m->socio_number,
                        'name'  => trim(($m->first_name ?? '') . ' ' . ($m->last_name_1 ?? '')) ?: ('Socio #' . $m->id),
                    ])->values()->all(),
            ];
        })->values()->all();
    }

    // --- helpers ---

    private function ensureWeekGenerated(Carbon $ref, SessionGenerator $generator): void
    {
        // generateWeek es idempotente (dedup por slot+fecha y respeta
        // is_modified), así que lo ejecutamos siempre que haya slots activos.
        // Antes se saltaba si la semana ya tenía ALGUNA sesión, lo que impedía
        // que un slot agregado después apareciera en una semana ya poblada.
        if (ScheduleSlot::where('active', true)->exists()) {
            $generator->generateWeek($ref);
        }
    }

    private function filteredSessions(Request $request)
    {
        return ClassSession::query()
            ->when($request->filled('instructor'), fn($q) =>
            $q->where(fn($w) => $w
                ->where('actual_instructor_id', $request->integer('instructor'))
                ->orWhere('scheduled_instructor_id', $request->integer('instructor'))))
            ->when($request->filled('program'), fn($q) =>
            $q->where('program_id', $request->integer('program')))
            ->when($request->filled('lane'), fn($q) =>
            $q->where('lane_id', $request->integer('lane')));
    }

    /**
     * Límites del lienzo del día en minutos desde medianoche. Se ajustan a las
     * clases del día (con 30 min de margen), con un rango por defecto razonable
     * si el día está vacío. Redondeados a la hora.
     */
    private function canvasBounds($sessions): array
    {
        // Ventana fija de jornada (fuente única: config/swimfit.php → horario).
        // El lienzo SIEMPRE muestra este rango completo (p. ej. 07:00–21:00),
        // sin importar a qué hora caiga la primera/última clase. Así una clase a
        // las 09:20 no colapsa la vista a 08:00–11:00.
        $dayStart = (int) config('swimfit.horario.inicio_min', 7 * 60);
        $dayEnd   = (int) config('swimfit.horario.fin_min', 21 * 60);

        if ($sessions->isEmpty()) {
            return [$dayStart, $dayEnd];
        }

        // Fallback: si alguna clase queda FUERA de la ventana fija, expandimos al
        // borde de hora para que nunca se recorte. Nunca encogemos por debajo de
        // la ventana de jornada.
        $starts = $sessions->map(fn($s) => $s->starts_at->hour * 60 + $s->starts_at->minute);
        $ends = $sessions->map(fn($s) =>
        $s->starts_at->hour * 60 + $s->starts_at->minute + $s->duration_min);

        $min = min($dayStart, ((int) floor($starts->min() / 60)) * 60);
        $max = max($dayEnd, ((int) ceil($ends->max() / 60)) * 60);

        $min = max(0, $min);
        $max = min(24 * 60, $max);

        return [$min, $max];
    }

    private function weekdayLabels(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];
    }
}
