<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Member;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Vista principal: rejilla HORAS × DÍAS (L-V). Cada celda es una clase.
     * Filtros opcionales por instructor, programa y carril. ?week=Y-m-d elige
     * la semana.
     */
    public function index(Request $request, SessionGenerator $generator)
    {
        $reference = $request->filled('week')
            ? Carbon::parse($request->week)
            : Carbon::now();

        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY);

        // Generar al vuelo si falta (igual que antes).
        $hasSessions = ClassSession::forWeek($reference)->exists();
        if (! $hasSessions && ScheduleSlot::where('active', true)->exists()) {
            $generator->generateWeek($reference);
        }

        $query = ClassSession::with([
                'program', 'lane', 'scheduledInstructor', 'actualInstructor',
                'members:id,first_name,last_name_1',
            ])
            ->forWeek($reference)
            // Filtros
            ->when($request->filled('instructor'), fn ($q) =>
                $q->where(fn ($w) => $w
                    ->where('actual_instructor_id', $request->integer('instructor'))
                    ->orWhere('scheduled_instructor_id', $request->integer('instructor'))))
            ->when($request->filled('program'), fn ($q) =>
                $q->where('program_id', $request->integer('program')))
            ->when($request->filled('lane'), fn ($q) =>
                $q->where('lane_id', $request->integer('lane')))
            ->orderBy('starts_at');

        $sessions = $query->get();

        // Construir la rejilla: filas = horas de inicio distintas; columnas = día.
        // grid[HH:mm][iso_weekday] = colección de sesiones.
        $grid = [];
        $times = [];
        foreach ($sessions as $s) {
            $t = $s->starts_at->format('H:i');
            $d = $s->starts_at->isoWeekday();
            $times[$t] = true;
            $grid[$t][$d][] = $s;
        }
        ksort($times);

        // Payload para el modal "mover socio": id, etiqueta y socios de cada
        // sesión. Se arma aquí (no en Blade) para no confundir a @json con los
        // corchetes de arrays anidados.
        $sessionsPayload = $sessions->map(fn ($s) => [
            'id'    => $s->id,
            'label' => ($s->program?->name ?? 'Clase') . ' · '
                     . $s->starts_at->isoFormat('ddd HH:mm') . ' · '
                     . ($s->actualInstructor?->name ?? 'sin instructor'),
            'members' => $s->members->map(fn ($m) => [
                'id'   => $m->id,
                'name' => trim($m->first_name . ' ' . $m->last_name_1),
            ])->values(),
        ])->values();

        return view('schedule.index', [
            'weekStart'   => $weekStart,
            'grid'        => $grid,
            'times'       => array_keys($times),
            'sessionsPayload' => $sessionsPayload,
            'prevWeek'    => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek'    => $weekStart->copy()->addWeek()->toDateString(),
            'weekdays'    => $this->weekdayLabels(),
            // Para filtros y "mover socio"
            'instructors' => Instructor::where('active', true)->orderBy('name')->get(),
            'programs'    => Program::where('active', true)->orderBy('name')->get(),
            'lanes'       => Lane::with('pool')->orderBy('position')->get(),
            'filters'     => $request->only(['instructor', 'program', 'lane']),
        ]);
    }

    public function template()
    {
        $this->authorize('move-classes');

        $slots = ScheduleSlot::with(['program', 'lane', 'instructor', 'members:id'])
            ->where('active', true)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->groupBy('weekday');

        return view('schedule.template', [
            'slots'       => $slots,
            'weekdays'    => $this->weekdayLabels(),
            'programs'    => Program::where('active', true)->orderBy('name')->get(),
            'instructors' => Instructor::where('active', true)->orderBy('name')->get(),
            'lanes'       => Lane::with('pool')->orderBy('pool_id')->orderBy('position')->get(),
        ]);
    }

    private function weekdayLabels(): array
    {
        return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
    }
}
