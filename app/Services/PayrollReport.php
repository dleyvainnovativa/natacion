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
            ->get(['id', 'scheduled_instructor_id', 'actual_instructor_id', 'instructor_id_2',
                'evaluations_count', 'starts_at', 'duration_min']);

        // Cierres (festivos / mantenimiento) del periodo: las sesiones que caen en
        // un cierre NO cuentan para el pago.
        $blackouts = \App\Models\Blackout::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->groupBy(fn ($b) => $b->date->toDateString());

        // Conteo por instructor. Cada clase impartida cuenta completa para quien
        // la dio (actual_instructor) Y para el co-maestro si lo hay: ambos cobran.
        // Las evaluaciones de la clase se pagan al que la impartió (actual).
        $tally = []; // id => ['taught'=>, 'substituted'=>, 'as_co'=>, 'evals'=>]
        foreach ($sessions as $s) {
            $dayBlk = $blackouts->get($s->starts_at->toDateString());
            if ($dayBlk && \App\Models\Blackout::covers($s, $dayBlk)) {
                continue; // día/horario inhábil: no se paga
            }

            if ($primary = $s->actual_instructor_id) {
                $tally[$primary] ??= ['taught' => 0, 'substituted' => 0, 'as_co' => 0, 'evals' => 0];
                $tally[$primary]['taught']++;
                $tally[$primary]['evals'] += (int) $s->evaluations_count;
                if ($s->scheduled_instructor_id !== $s->actual_instructor_id) {
                    $tally[$primary]['substituted']++;
                }
            }
            if ($co = $s->instructor_id_2) {
                $tally[$co] ??= ['taught' => 0, 'substituted' => 0, 'as_co' => 0, 'evals' => 0];
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

            $rate     = (float) $instructor->pay_per_class;
            $evalRate = (float) $instructor->evaluation_price;
            $evals    = $t['evals'];
            $evalPay  = $evals * $evalRate;

            return [
                'instructor'      => $instructor,
                'classes_taught'  => $t['taught'],
                'substituted_in'  => $t['substituted'],
                'as_co'           => $t['as_co'],
                'own_classes'     => $t['taught'] - $t['substituted'] - $t['as_co'],
                'pay_per_class'   => $rate,
                'class_pay'       => $t['taught'] * $rate,
                'evaluations'     => $evals,
                'evaluation_price'=> $evalRate,
                'evaluation_pay'  => $evalPay,
                'total'           => $t['taught'] * $rate + $evalPay,
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
