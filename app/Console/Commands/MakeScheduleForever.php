<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use App\Models\ScheduleSlot;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CUTOVER al horario "para siempre".
 *
 * Objetivo: dejar UN horario base que se repite para siempre, SIN perder clases.
 *
 * A diferencia de la primera versión (que tomaba un solo mes como base y jubilaba
 * todo lo demás —y por eso borraba casi todo cuando el horario estaba repartido
 * en varias plantillas—), esta versión:
 *
 *   1. Junta TODAS las clases (slots) y las agrupa por IDENTIDAD
 *      (día + hora + carril + programa + nivel).
 *   2. Por cada grupo deja UNA sola clase "ganadora" (la de roster más grande),
 *      fusionando en ella los socios de las repetidas. Esa clase pasa a ser
 *      "para siempre". Las repetidas REALES (mismo día/hora/carril/programa) se
 *      jubilan. Si una clase no tiene repetidas, se conserva tal cual.
 *   3. Borra sólo las sesiones futuras de las clases jubiladas (no las manuales).
 *   4. Regenera el horizonte.
 *
 * Así, si tu horario no tiene duplicados reales, NO se pierde nada: todas las
 * clases distintas quedan "para siempre".
 *
 *   php artisan schedule:make-forever --dry-run
 *   php artisan schedule:make-forever
 *   php artisan schedule:make-forever --include-inactive   # recuperar lo jubilado
 *   php artisan schedule:make-forever --from=2026-11-10
 */
class MakeScheduleForever extends Command
{
    protected $signature = 'schedule:make-forever
                            {--dry-run : Sólo reporta lo que haría, sin cambiar nada}
                            {--include-inactive : Considerar también los slots inactivos (para RECUPERAR clases que una corrida anterior jubiló)}
                            {--from= : Fecha de corte Y-m-d (default: lunes de esta semana)}';

    protected $description = 'Consolida el horario a uno base "para siempre" sin perder clases (con --dry-run y recuperación).';

    public function handle(SessionGenerator $generator): int
    {
        $dry      = (bool) $this->option('dry-run');
        $incInact = (bool) $this->option('include-inactive');

        $cut = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        // Universo de slots a considerar.
        $slots = ScheduleSlot::with('members:id')
            ->when(! $incInact, fn ($q) => $q->where('active', true))
            ->get();

        if ($slots->isEmpty()) {
            $this->warn($incInact
                ? 'No hay slots en el sistema.'
                : 'No hay slots activos. Si una corrida anterior jubiló tus clases, usa --include-inactive para recuperarlas.');
            return self::SUCCESS;
        }

        // Agrupar por identidad de clase.
        $groups = $slots->groupBy(fn (ScheduleSlot $s) => $this->identity($s));

        $winners = collect();
        $losers  = collect();
        $mergedRosters = [];   // winnerId => [memberIds]

        foreach ($groups as $group) {
            $winner = $this->pickWinner($group);
            $winners->push($winner);

            // Unir rosters de todo el grupo en el ganador.
            $ids = $group->flatMap(fn (ScheduleSlot $s) => $s->members->pluck('id'))->unique()->values()->all();
            $mergedRosters[$winner->id] = $ids;

            foreach ($group as $s) {
                if ($s->id !== $winner->id) {
                    $losers->push($s);
                }
            }
        }

        // Sesiones futuras a borrar: de los perdedores, no modificadas a mano.
        $loserIds = $losers->pluck('id')->all();
        $futureToDelete = $loserIds
            ? ClassSession::whereIn('schedule_slot_id', $loserIds)
                ->where('starts_at', '>=', $cut)->where('is_modified', false)->count()
            : 0;

        // --- Reporte ---
        $this->line('');
        $this->info('Plan de consolidación a horario "para siempre" (sin perder clases)');
        $this->line('  Fecha de corte        : ' . $cut->format('d/m/Y'));
        $this->line('  Considerando slots    : ' . ($incInact ? 'ACTIVOS + INACTIVOS (recuperación)' : 'sólo activos'));
        $this->line('  Clases distintas      : ' . $winners->count() . '  → quedan "para siempre"');
        $this->line('  Duplicados a jubilar  : ' . $losers->count());
        $this->line('  Sesiones futuras que se borran: ' . $futureToDelete . ' (sólo de duplicados, sin tocar lo manual)');
        $this->reportByWeekday($winners);
        $this->line('');

        if ($dry) {
            $this->comment('DRY-RUN: no se cambió nada. Si el reparto por día cuadra con tu horario real, '
                . 'corre el mismo comando sin --dry-run' . ($incInact ? ' (manteniendo --include-inactive).' : '.'));
            return self::SUCCESS;
        }

        if (! $this->confirm('Esto deja ' . $winners->count() . ' clases "para siempre", jubila ' . $losers->count()
            . ' duplicados y borra ' . $futureToDelete . ' sesiones futuras. ¿Continuar?', false)) {
            $this->line('Cancelado. No se cambió nada.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($winners, $losers, $mergedRosters, $cut) {
            // 1. Promover ganadores a "para siempre" + fusionar rosters.
            foreach ($winners as $w) {
                $w->update([
                    'schedule_template_id' => null,
                    'effective_from'       => $cut->toDateString(),
                    'effective_to'         => null,
                    'source'               => 'base',
                    'active'               => true,
                ]);

                $ids = $mergedRosters[$w->id] ?? [];
                if ($ids) {
                    // Aditivo: nunca quita socios ya inscritos a mano.
                    $w->members()->syncWithoutDetaching($ids);
                    ClassSession::where('schedule_slot_id', $w->id)
                        ->where('starts_at', '>=', $cut)
                        ->get()
                        ->each(fn (ClassSession $s) => $s->members()->syncWithoutDetaching($ids));
                }
            }

            // 2. Jubilar duplicados (conserva historial).
            if ($losers->isNotEmpty()) {
                ScheduleSlot::whereIn('id', $losers->pluck('id'))->update([
                    'active'       => false,
                    'effective_to' => $cut->copy()->subDay()->toDateString(),
                ]);

                // 3. Borrar sus sesiones futuras no manuales.
                ClassSession::whereIn('schedule_slot_id', $losers->pluck('id'))
                    ->where('starts_at', '>=', $cut)
                    ->where('is_modified', false)
                    ->delete();
            }
        });

        // 4. Regenerar el horizonte desde las clases "para siempre".
        $gen = $generator->generateHorizon();

        $this->info('Listo. Horario consolidado a "para siempre".');
        $this->line('  Regeneración: ' . $gen['created'] . ' sesiones creadas, ' . $gen['skipped'] . ' ya existían.');
        $this->comment('Abre el calendario de las próximas semanas y confirma que están todas tus clases.');

        return self::SUCCESS;
    }

    /** Identidad de clase: día|hora|carril|programa|nivel. */
    private function identity(ScheduleSlot $s): string
    {
        return $s->weekday
            . '|' . substr((string) $s->start_time, 0, 5)
            . '|' . ($s->lane_id ?? 'x')
            . '|' . $s->program_id
            . '|' . ($s->level_id ?? 'x');
    }

    /**
     * Ganador del grupo: el de roster más grande; a igualdad, el activo, luego el
     * que ya es "para siempre" (sin plantilla), luego el id más alto (más nuevo).
     */
    private function pickWinner(Collection $group): ScheduleSlot
    {
        return $group->sort(function (ScheduleSlot $a, ScheduleSlot $b) {
            return [$b->members->count(), $b->active ? 1 : 0, $b->schedule_template_id === null ? 1 : 0, $b->id]
                <=> [$a->members->count(), $a->active ? 1 : 0, $a->schedule_template_id === null ? 1 : 0, $a->id];
        })->first();
    }

    private function reportByWeekday(Collection $winners): void
    {
        $dn = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $by = $winners->groupBy('weekday')->map->count();
        $line = '  reparto por día       : ';
        foreach ([1, 2, 3, 4, 5, 6, 7] as $d) {
            $line .= $dn[$d] . ':' . ($by[$d] ?? 0) . '  ';
        }
        $this->line($line);
    }
}
