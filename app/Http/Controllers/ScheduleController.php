<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Vista principal: sesiones fechadas de una semana, en columnas L-V.
     * ?week=Y-m-d elige la semana; por defecto la actual.
     */
    public function index(Request $request, SessionGenerator $generator)
    {
        $reference = $request->filled('week')
            ? Carbon::parse($request->week)
            : Carbon::now();

        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY);

        // Si la semana no tiene sesiones y hay slots, generarla al vuelo para
        // que la vista nunca aparezca vacía por falta del cron.
        $hasSessions = ClassSession::forWeek($reference)->exists();
        if (! $hasSessions && ScheduleSlot::where('active', true)->exists()) {
            $generator->generateWeek($reference);
        }

        $sessions = ClassSession::with([
                'program', 'lane', 'scheduledInstructor', 'actualInstructor', 'members:id',
            ])
            ->forWeek($reference)
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn ($s) => $s->starts_at->isoWeekday()); // 1..7

        return view('schedule.index', [
            'weekStart' => $weekStart,
            'sessions'  => $sessions,
            'prevWeek'  => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek'  => $weekStart->copy()->addWeek()->toDateString(),
            'weekdays'  => $this->weekdayLabels(),
        ]);
    }

    /**
     * Vista de plantilla: los slots recurrentes que el staff edita.
     */
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

    /** Etiquetas L-V (ISO 1..5). Sábado/domingo no se usan hoy. */
    private function weekdayLabels(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
        ];
    }
}
