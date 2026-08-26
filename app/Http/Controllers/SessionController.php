<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Services\ConflictDetector;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Mueve una sesión. scope = 'date' (solo esta fecha) o 'series' (el slot
     * completo, hacia adelante). Devuelve JSON con avisos de conflicto; no
     * bloquea (los avisos se muestran, la acción se aplica).
     */
    public function move(Request $request, ClassSession $session, ConflictDetector $detector)
    {
        $this->authorize('move-classes');

        $data = $request->validate([
            'scope'         => ['required', 'in:date,series'],
            'starts_at'     => ['required', 'date'],
            'lane_id'       => ['nullable', 'exists:lanes,id'],
            'instructor_id' => ['nullable', 'exists:instructors,id'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $newStart = Carbon::parse($data['starts_at']);

        $warnings = $detector->check([
            'id'            => $session->id,
            'starts_at'     => $newStart,
            'duration_min'  => $session->duration_min,
            'lane_id'       => $data['lane_id'] ?? $session->lane_id,
            'instructor_id' => $data['instructor_id'] ?? $session->scheduled_instructor_id,
            'program_id'    => $session->program_id,
            'member_ids'    => $session->members()->pluck('members.id')->all(),
        ]);

        if ($data['scope'] === 'series' && $session->schedule_slot_id) {
            // Editar el slot mueve la serie hacia adelante (sesiones futuras
            // no modificadas se regenerarán en la nueva posición).
            $slot = $session->slot;
            $slot->update([
                'weekday'       => $newStart->isoWeekday(),
                'start_time'    => $newStart->format('H:i'),
                'lane_id'       => $data['lane_id'] ?? $slot->lane_id,
                'instructor_id' => $data['instructor_id'] ?? $slot->instructor_id,
            ]);
            // Actualizar también esta sesión concreta para reflejarlo ya.
            $session->update([
                'starts_at'               => $newStart,
                'lane_id'                 => $data['lane_id'] ?? $session->lane_id,
                'scheduled_instructor_id' => $data['instructor_id'] ?? $session->scheduled_instructor_id,
                'actual_instructor_id'    => $data['instructor_id'] ?? $session->actual_instructor_id,
                'notes'                   => $data['notes'] ?? $session->notes,
            ]);
        } else {
            // Solo esta fecha: marcar como modificada para que el generador no
            // la pise.
            $session->update([
                'starts_at'               => $newStart,
                'lane_id'                 => $data['lane_id'] ?? $session->lane_id,
                'scheduled_instructor_id' => $data['instructor_id'] ?? $session->scheduled_instructor_id,
                'actual_instructor_id'    => $data['instructor_id'] ?? $session->actual_instructor_id,
                'notes'                   => $data['notes'] ?? $session->notes,
                'is_modified'             => true,
            ]);
        }

        return response()->json([
            'ok'       => true,
            'warnings' => $warnings,
            'message'  => $data['scope'] === 'series'
                ? 'Serie movida. Aplica a esta y las próximas semanas.'
                : 'Clase movida solo para esta fecha.',
        ]);
    }

    public function cancel(Request $request, ClassSession $session)
    {
        $this->authorize('move-classes');

        $session->update([
            'status'      => 'cancelled',
            'is_modified' => true,
            'notes'       => $request->input('notes', $session->notes),
        ]);

        return response()->json(['ok' => true, 'message' => 'Clase cancelada.']);
    }

    /** Endpoint de sólo-lectura para previsualizar conflictos antes de mover. */
    public function checkConflicts(Request $request, ClassSession $session, ConflictDetector $detector)
    {
        $this->authorize('move-classes');

        $data = $request->validate([
            'starts_at'     => ['required', 'date'],
            'lane_id'       => ['nullable', 'exists:lanes,id'],
            'instructor_id' => ['nullable', 'exists:instructors,id'],
        ]);

        $warnings = $detector->check([
            'id'            => $session->id,
            'starts_at'     => Carbon::parse($data['starts_at']),
            'duration_min'  => $session->duration_min,
            'lane_id'       => $data['lane_id'] ?? $session->lane_id,
            'instructor_id' => $data['instructor_id'] ?? $session->scheduled_instructor_id,
            'program_id'    => $session->program_id,
            'member_ids'    => $session->members()->pluck('members.id')->all(),
        ]);

        return response()->json(['warnings' => $warnings]);
    }
}
