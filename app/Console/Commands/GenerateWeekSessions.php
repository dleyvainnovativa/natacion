<?php

namespace App\Console\Commands;

use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Materializa las sesiones de una semana desde los slots activos.
 *
 *   php artisan schedule:generate-week            # semana actual
 *   php artisan schedule:generate-week --next     # próxima semana
 *   php artisan schedule:generate-week --date=2026-09-01
 *
 * Programado en routes/console.php para correr cada lunes y dejar lista la
 * semana siguiente. Idempotente: se puede correr varias veces sin duplicar.
 */
class GenerateWeekSessions extends Command
{
    protected $signature = 'schedule:generate-week
                            {--next : Generar la próxima semana en vez de la actual}
                            {--date= : Fecha (Y-m-d) dentro de la semana a generar}';

    protected $description = 'Genera las sesiones fechadas de una semana desde los slots recurrentes.';

    public function handle(SessionGenerator $generator): int
    {
        $reference = match (true) {
            (bool) $this->option('date') => Carbon::parse($this->option('date')),
            (bool) $this->option('next') => Carbon::now()->addWeek(),
            default                      => Carbon::now(),
        };

        $result = $generator->generateWeek($reference);

        $this->info(sprintf(
            'Semana de %s: %d sesiones creadas, %d ya existían.',
            $reference->startOfWeek(Carbon::MONDAY)->format('d/m/Y'),
            $result['created'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
