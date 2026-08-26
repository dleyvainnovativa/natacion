<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Instructor;
use App\Services\SubstitutionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InstructorAttendanceController extends Controller
{
    /**
     * Sesiones del día con instructor asignado, para que el coordinador marque
     * puntualidad y, si hace falta, asigne suplente.
     */
    public function index(Request $request)
    {
        $this->authorize('mark-instructor-attendance');

        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $sessions = ClassSession::with([
                'program', 'lane', 'scheduledInstructor', 'actualInstructor', 'instructorAttendance',
            ])
            ->whereDate('starts_at', $date->toDateString())
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('scheduled_instructor_id')
            ->orderBy('starts_at')
            ->get();

        return view('attendance.instructors.index', [
            'sessions'    => $sessions,
            'date'        => $date,
            'prevDate'    => $date->copy()->subDay()->toDateString(),
            'nextDate'    => $date->copy()->addDay()->toDateString(),
            'instructors' => Instructor::where('active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Registra la asistencia de un instructor a una sesión. Si llega tarde o
     * falta y se asigna suplente, el servicio reescribe actual_instructor_id
     * (base del pago en T4). Devuelve JSON con avisos.
     */
    public function store(ClassSession $session, Request $request, SubstitutionService $service)
    {
        $this->authorize('mark-instructor-attendance');

        $data = $request->validate([
            'status'        => ['required', 'in:on_time,late,absent'],
            'substitute_id' => ['nullable', 'exists:instructors,id'],
        ]);

        // El suplente no puede ser el mismo instructor programado.
        if (($data['substitute_id'] ?? null) === $session->scheduled_instructor_id) {
            $data['substitute_id'] = null;
        }

        $result = $service->record(
            $session,
            $data['status'],
            $data['substitute_id'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'ok'       => true,
            'warnings' => $result['warnings'],
            'actual_instructor' => $session->fresh()->actualInstructor?->name,
            'message'  => 'Asistencia del instructor registrada.',
        ]);
    }
}
