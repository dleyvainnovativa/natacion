<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Program;
use App\Models\ScheduleTemplate;
use App\Services\EnrollmentService;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Alta rápida de clase desde el calendario (clic en un hueco, estilo Google
 * Calendar). Un solo endpoint que, según el alcance:
 *   - weekly: crea un slot recurrente en la plantilla del mes de esa fecha
 *             (se repite cada semana) y materializa sus sesiones.
 *   - once:   crea UNA sola sesión fechada (sin slot), marcada is_modified
 *             para que el generador semanal nunca la toque.
 */
class QuickClassController extends Controller
{
    public function store(Request $request, SessionGenerator $generator, EnrollmentService $enroller)
    {
        $this->authorize('move-classes');

        $data = $request->validate([
            'scope'         => ['required', 'in:weekly,once'],
            'program_id'    => ['required', 'exists:programs,id'],
            'level_id'       => ['nullable', 'exists:program_levels,id'],
            'instructor_id'   => ['nullable', 'exists:instructors,id'],
            'instructor_id_2' => ['nullable', 'exists:instructors,id', 'different:instructor_id'],
            'lane_id'       => ['nullable', 'exists:lanes,id'],
            'start_date'    => ['required', 'date'],
            'start_time'    => ['required', 'date_format:H:i'],
            'duration_min'  => ['nullable', 'integer', 'min:5', 'max:240'],
            'member_ids'    => ['nullable', 'array'],
            'member_ids.*'  => ['integer', 'exists:members,id'],
        ]);

        $date = Carbon::parse($data['start_date']);
        $level = ! empty($data['level_id']) ? \App\Models\ProgramLevel::find($data['level_id']) : null;
        // Duración: la del nivel si se eligió, si no la del programa.
        $dur  = $data['duration_min'] ?? $level?->duration_min ?? Program::find($data['program_id'])?->duration_min ?? 30;
        $memberIds = $data['member_ids'] ?? [];

        if ($data['scope'] === 'weekly') {
            $template = ScheduleTemplate::resolveFor($date);
            $slot = $template->slots()->create([
                'program_id'      => $data['program_id'],
                'level_id'        => $data['level_id'] ?? null,
                'instructor_id'   => $data['instructor_id'] ?? null,
                'instructor_id_2' => $data['instructor_id_2'] ?? null,
                'lane_id'         => $data['lane_id'] ?? null,
                'weekday'         => $date->isoWeekday(),
                'start_time'    => $data['start_time'],
                'duration_min'  => $dur,
                'active'        => true,
            ]);

            // Materializar desde la semana actual (o el inicio del mes) hasta fin
            // de mes, para que aparezca de inmediato.
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd   = $date->copy()->endOfMonth();
            $weekStart  = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $from = $weekStart->greaterThan($monthStart) ? $weekStart : $monthStart;
            if ($from->lte($monthEnd)) {
                $generator->generateForSlot($slot, $from, $monthEnd);
            }

            if ($memberIds) {
                $enroller->syncSlotRoster($slot, $memberIds);
            }

            $msg = 'Clase semanal creada (se repite cada semana).';
        } else {
            $session = ClassSession::create([
                'schedule_slot_id'        => null,
                'program_id'              => $data['program_id'],
                'level_id'                => $data['level_id'] ?? null,
                'lane_id'                 => $data['lane_id'] ?? null,
                'scheduled_instructor_id' => $data['instructor_id'] ?? null,
                'actual_instructor_id'    => $data['instructor_id'] ?? null,
                'instructor_id_2'         => $data['instructor_id_2'] ?? null,
                'starts_at'               => $date->copy()->setTimeFromTimeString($data['start_time']),
                'duration_min'            => $dur,
                'status'                  => 'scheduled',
                'is_modified'             => true,   // el generador nunca la toca
            ]);

            if ($memberIds) {
                $session->members()->sync($memberIds);
            }

            $msg = 'Clase creada sólo para este día.';
        }

        return back()->with('ok', $msg);
    }
}
