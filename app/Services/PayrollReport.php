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

        // Todas las sesiones impartidas en el rango, con instructor real.
        $sessions = ClassSession::query()
            ->where('status', 'held')
            ->whereNotNull('actual_instructor_id')
            ->whereBetween('starts_at', [$from, $to])
            ->get(['id', 'scheduled_instructor_id', 'actual_instructor_id']);

        // Agrupar por instructor real.
        $byInstructor = $sessions->groupBy('actual_instructor_id');

        $instructors = Instructor::whereIn('id', $byInstructor->keys())
            ->get()
            ->keyBy('id');

        return $byInstructor->map(function (Collection $group, $instructorId) use ($instructors) {
            $instructor = $instructors->get($instructorId);
            if (! $instructor) {
                return null;
            }

            $taught = $group->count();
            // Clases que cubrió como suplente (era el real, no el programado).
            $substituted = $group->filter(
                fn ($s) => $s->scheduled_instructor_id !== $s->actual_instructor_id
            )->count();

            $rate = (float) $instructor->pay_per_class;

            return [
                'instructor'     => $instructor,
                'classes_taught' => $taught,
                'substituted_in' => $substituted,
                'own_classes'    => $taught - $substituted,
                'pay_per_class'  => $rate,
                'total'          => $taught * $rate,
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
