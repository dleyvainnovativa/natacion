<?php

namespace App\Console\Commands;

use App\Services\SessionGenerator;
use Illuminate\Console\Command;

/**
 * Materializa el horizonte hacia adelante (semana actual + próximas N) desde
 * los slots recurrentes VIGENTES. Es el comando del modelo "para siempre":
 * corre cada lunes y mantiene siempre llenas las próximas semanas.
 *
 *   php artisan schedule:generate-forward
 *   php artisan schedule:generate-forward --weeks=12
 *
 * Idempotente: se puede correr varias veces sin duplicar.
 */
class GenerateForwardSessions extends Command
{
    protected $signature = 'schedule:generate-forward
                            {--weeks= : Cuántas semanas hacia adelante (default: horizonte configurado)}';

    protected $description = 'Genera las sesiones del horizonte (semana actual y próximas) desde los slots vigentes.';

    public function handle(SessionGenerator $generator): int
    {
        $weeks = $this->option('weeks') !== null ? (int) $this->option('weeks') : null;

        $result = $generator->generateHorizon($weeks);

        $this->info(sprintf(
            'Horizonte (%d semanas): %d sesiones creadas, %d ya existían.',
            $weeks ?? SessionGenerator::HORIZON_WEEKS,
            $result['created'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
