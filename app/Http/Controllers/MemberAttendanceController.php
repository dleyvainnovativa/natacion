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
     * Lista las sesiones donde el usuario puede pasar lista. Un instructor ve
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
