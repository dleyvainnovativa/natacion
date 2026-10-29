<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Instructor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporte de pago a instructores.
 *
 * Regla (definida en T3): se paga por clase IMPARTIDA (status = held), contando
 * por actual_instructor_id. Así el suplente cobra las clases que cubrió y el
 * instructor ausente-sin-suplente no cobra la que no se dio.
 *
 * Total por instructor = (nº de clases held como actual) × pay_per_class.
 */
class PayrollReport
{
    /**
     * @return Collection<int, array{
     *   instructor: Instructor,
     *   classes_taught: int,
     *   substituted_in: int,
     *   own_classes: int,
     *   pay_per_class: float,
     *   total: float
     * }>
     */
    public function build(Carbon $from, Carbon $to): Collection
    {
        $from = $from->copy()->startOfDay();
        $to   = $to->copy()->endOfDay();

        // Todas las sesiones impartidas en el rango.
        $sessions = ClassSession::query()
            ->where('status', 'held')
            ->whereBetween('starts_at', [$from, $to])
            ->get(['id', 'scheduled_instructor_id', 'actual_instructor_id', 'instructor_id_2']);

        // Conteo por instructor. Cada clase impartida cuenta completa para quien
        // la dio (actual_instructor) Y para el co-maestro si lo hay: ambos cobran.
        $tally = []; // id => ['taught'=>, 'substituted'=>, 'as_co'=>]
        foreach ($sessions as $s) {
            if ($primary = $s->actual_instructor_id) {
                $tally[$primary] ??= ['taught' => 0, 'substituted' => 0, 'as_co' => 0];
                $tally[$primary]['taught']++;
                if ($s->scheduled_instructor_id !== $s->actual_instructor_id) {
                    $tally[$primary]['substituted']++;
                }
            }
            if ($co = $s->instructor_id_2) {
                $tally[$co] ??= ['taught' => 0, 'substituted' => 0, 'as_co' => 0];
                $tally[$co]['taught']++;
                $tally[$co]['as_co']++;
            }
        }

        $instructors = Instructor::whereIn('id', array_keys($tally))->get()->keyBy('id');

        return collect($tally)->map(function (array $t, $instructorId) use ($instructors) {
            $instructor = $instructors->get($instructorId);
            if (! $instructor) {
                return null;
            }

            $rate = (float) $instructor->pay_per_class;

            return [
                'instructor'     => $instructor,
                'classes_taught' => $t['taught'],
                'substituted_in' => $t['substituted'],
                'as_co'          => $t['as_co'],
                'own_classes'    => $t['taught'] - $t['substituted'] - $t['as_co'],
                'pay_per_class'  => $rate,
                'total'          => $t['taught'] * $rate,
            ];
        })
        ->filter()
        ->sortByDesc('total')
        ->values();
    }

    /** Gran total del periodo. */
    public function grandTotal(Collection $rows): float
    {
        return (float) $rows->sum('total');
    }
}
