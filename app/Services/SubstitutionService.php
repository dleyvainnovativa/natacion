<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\InstructorAttendance;
use Carbon\Carbon;

/**
 * Punto único donde se decide "quién dio realmente la clase".
 *
 * Regla de negocio: si el instructor programado llega tarde o falta, el
 * coordinador asigna un suplente. Ese suplente es quien cobra la clase (T4),
 * así que aquí se reescribe class_sessions.actual_instructor_id. Nunca se
 * toca scheduled_instructor_id (queda el registro de quién debía darla).
 *
 * Toda modificación de actual_instructor_id pasa por aquí para que la lógica
 * de pago no se fragmente entre controladores.
 */
class SubstitutionService
{
    public function __construct(private ConflictDetector $detector) {}

    /**
     * Registra la asistencia del instructor a una sesión y ajusta quién la
     * impartió.
     *
     * @param  string  $status  on_time | late | absent
     * @param  int|null  $substituteId  requerido si late/absent con reemplazo
     * @return array{attendance: InstructorAttendance, warnings: string[]}
     */
    public function record(
        ClassSession $session,
        string $status,
        ?int $substituteId,
        ?int $markedBy
    ): array {
        $warnings = [];

        // Determinar quién impartió realmente.
        $actualInstructorId = $session->scheduled_instructor_id;

        if (in_array($status, ['late', 'absent'], true) && $substituteId) {
            $actualInstructorId = $substituteId;

            // Avisar (no bloquear) si el suplente ya da otra clase a esa hora.
            if ($this->substituteIsBusy($substituteId, $session)) {
                $warnings[] = 'El suplente ya tiene otra clase a esa hora.';
            }
        }

        // Guardar/actualizar la asistencia del instructor.
        $attendance = InstructorAttendance::updateOrCreate(
            ['class_session_id' => $session->id],
            [
                'instructor_id'            => $session->scheduled_instructor_id,
                'status'                   => $status,
                'substitute_instructor_id' => $substituteId,
                'marked_by'                => $markedBy,
                'marked_at'                => Carbon::now(),
            ]
        );

        // Reescribir quién impartió y marcar la sesión como impartida.
        $session->update([
            'actual_instructor_id' => $actualInstructorId,
            'status'               => $status === 'absent' && ! $substituteId
                ? $session->status         // ausente sin suplente: no se dio (queda igual)
                : 'held',
        ]);

        return compact('attendance', 'warnings');
    }

    /** ¿El suplente ya está asignado a otra clase que se traslapa? */
    private function substituteIsBusy(int $substituteId, ClassSession $session): bool
    {
        $warnings = $this->detector->check([
            'id'            => $session->id,
            'starts_at'     => $session->starts_at,
            'duration_min'  => $session->duration_min,
            'lane_id'       => null,          // solo nos interesa el choque de instructor
            'instructor_id' => $substituteId,
            'program_id'    => $session->program_id,
            'member_ids'    => [],
        ]);

        // El detector devuelve un aviso de instructor si hay choque.
        foreach ($warnings as $w) {
            if (str_contains($w, 'instructor')) {
                return true;
            }
        }

        return false;
    }
}
