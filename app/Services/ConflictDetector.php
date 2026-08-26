<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Program;
use Carbon\Carbon;

/**
 * Detecta conflictos de una sesión (propuesta o existente) contra las demás
 * sesiones del mismo día. Devuelve AVISOS; nunca bloquea (decisión de diseño).
 *
 * Comprueba cuatro cosas:
 *  1. Carril ocupado por otra clase a la misma hora (traslape).
 *  2. Instructor dando dos clases a la vez.
 *  3. Cupo del carril excedido según el programa (2 adultos / 6-7 niños).
 *  4. Un socio del roster inscrito en otra clase a la misma hora.
 *
 * Dos intervalos [aStart,aEnd) y [bStart,bEnd) se traslapan si
 * aStart < bEnd && bStart < aEnd.
 */
class ConflictDetector
{
    /**
     * @param  array  $proposal  [
     *     'id' => ?int (excluir esta sesión de la comparación),
     *     'starts_at' => Carbon,
     *     'duration_min' => int,
     *     'lane_id' => ?int,
     *     'instructor_id' => ?int,   // scheduled instructor
     *     'program_id' => int,
     *     'member_ids' => int[],     // roster propuesto
     *   ]
     * @return string[]  Lista de mensajes de aviso (vacía si no hay conflicto).
     */
    public function check(array $proposal): array
    {
        $warnings = [];

        $start = $proposal['starts_at'] instanceof Carbon
            ? $proposal['starts_at']
            : Carbon::parse($proposal['starts_at']);
        $end = $start->copy()->addMinutes($proposal['duration_min']);

        // Sesiones del mismo día, excluyendo la propia y las canceladas.
        $sameDay = ClassSession::with('members:id', 'program:id,name,lane_capacity')
            ->whereDate('starts_at', $start->toDateString())
            ->where('status', '!=', 'cancelled')
            ->when($proposal['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get();

        $overlapping = $sameDay->filter(function (ClassSession $other) use ($start, $end) {
            $oStart = $other->starts_at;
            $oEnd   = $other->endsAt();
            return $start->lt($oEnd) && $oStart->lt($end); // traslape estricto
        });

        // 1. Carril ocupado.
        if (! empty($proposal['lane_id'])) {
            $laneClash = $overlapping->firstWhere('lane_id', $proposal['lane_id']);
            if ($laneClash) {
                $warnings[] = sprintf(
                    'El carril ya tiene otra clase a esa hora (%s).',
                    $laneClash->starts_at->format('H:i')
                );
            }
        }

        // 2. Instructor ocupado.
        if (! empty($proposal['instructor_id'])) {
            $instrClash = $overlapping->first(fn (ClassSession $o) =>
                $o->scheduled_instructor_id === $proposal['instructor_id']
                || $o->actual_instructor_id === $proposal['instructor_id']
            );
            if ($instrClash) {
                $warnings[] = 'El instructor ya está asignado a otra clase a esa hora.';
            }
        }

        // 3. Cupo del carril según programa.
        $program = Program::find($proposal['program_id']);
        $rosterCount = count($proposal['member_ids'] ?? []);
        if ($program && $rosterCount > $program->lane_capacity) {
            $warnings[] = sprintf(
                'El roster (%d) supera el cupo del programa %s (%d por carril).',
                $rosterCount, $program->name, $program->lane_capacity
            );
        }

        // 4. Socios con doble reserva a la misma hora.
        $proposedMembers = array_flip($proposal['member_ids'] ?? []);
        if ($proposedMembers) {
            $doubled = [];
            foreach ($overlapping as $other) {
                foreach ($other->members as $m) {
                    if (isset($proposedMembers[$m->id])) {
                        $doubled[$m->id] = true;
                    }
                }
            }
            if ($doubled) {
                $warnings[] = sprintf(
                    '%d socio(s) del roster ya tienen otra clase a esa hora.',
                    count($doubled)
                );
            }
        }

        return $warnings;
    }
}
