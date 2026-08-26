<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Reporte de control de socios: compara los días a los que un socio tiene
 * derecho (según su tipo de socio) contra las clases recurrentes que tiene
 * asignadas (roster de slots).
 *
 * "Asignadas" = nº de slots recurrentes en los que el socio está en el roster.
 * Eso es lo que significa "3 días/semana": tres slots recurrentes. No se cuenta
 * por sesión fechada, porque una semana con feriado no debe marcar a nadie.
 *
 * Devuelve solo los socios con discrepancia (bajo o sobre lo asignado). Los
 * tipos "especiales" (BECA, ACUERDO, PAGO POR CLASE) y los que no tienen
 * days_per_week se omiten: no tienen un derecho fijo que comparar.
 */
class MemberControlReport
{
    /**
     * @return array{
     *   under: Collection, over: Collection, ok_count: int, unrated_count: int
     * }
     */
    public function build(): array
    {
        $members = Member::query()
            ->with('membershipType')
            ->where('status', 'ALTA')
            ->withCount('slotAssignments')  // nº de slots donde está en el roster
            ->get();

        $under = collect();
        $over  = collect();
        $okCount = 0;
        $unratedCount = 0;

        foreach ($members as $member) {
            $entitled = $member->membershipType?->days_per_week;

            // Sin derecho fijo que comparar (especiales, sin tipo, etc.).
            if ($entitled === null) {
                $unratedCount++;
                continue;
            }

            $assigned = $member->slot_assignments_count;
            $row = [
                'member'   => $member,
                'entitled' => $entitled,
                'assigned' => $assigned,
                'diff'     => $assigned - $entitled,
            ];

            if ($assigned < $entitled) {
                $under->push($row);
            } elseif ($assigned > $entitled) {
                $over->push($row);
            } else {
                $okCount++;
            }
        }

        return [
            'under'         => $under->sortBy('assigned')->values(),
            'over'          => $over->sortByDesc('diff')->values(),
            'ok_count'      => $okCount,
            'unrated_count' => $unratedCount,
        ];
    }
}
