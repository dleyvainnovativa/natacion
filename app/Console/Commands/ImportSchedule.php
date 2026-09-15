<?php

namespace App\Console\Commands;

use App\Services\ScheduleImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Importa las clases y rosters desde el archivo HORARIO.
 *
 *   php artisan schedule:import "storage/app/HORARIO JULIO 3.xlsx"
 *   php artisan schedule:import "…HORARIO JULIO 3.xlsx" --month=2026-07
 *
 * Requiere que los socios ya estén importados (T1), porque el roster liga por
 * número de socio. Importa hacia la plantilla del mes indicado (o el actual).
 * Idempotente: re-importar reemplaza los slots source=horario DE ESE MES.
 */
class ImportSchedule extends Command
{
    protected $signature = 'schedule:import
                            {path : Ruta al archivo HORARIO .xlsx}
                            {--month= : Mes destino en formato YYYY-MM (default: mes actual)}';

    protected $description = 'Importa clases y rosters desde el archivo HORARIO (.xlsx) hacia la plantilla de un mes.';

    public function handle(ScheduleImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");
            return self::FAILURE;
        }

        $monthOpt = $this->option('month');
        $monthRef = null;
        if ($monthOpt) {
            if (! preg_match('/^\d{4}-\d{2}$/', $monthOpt)) {
                $this->error('El formato de --month debe ser YYYY-MM (p. ej. 2026-07).');
                return self::FAILURE;
            }
            $monthRef = Carbon::createFromFormat('Y-m-d', $monthOpt . '-01');
        }

        $this->info('Importando horario…');
        $r = $importer->import($path, $monthRef);

        $this->info(sprintf(
            'Plantilla "%s": %d clases -> %d slots; %d vínculos de roster; %d filas omitidas.',
            $r['template'],
            $r['classes'],
            $r['slots'],
            $r['roster_links'],
            $r['skipped']
        ));

        foreach ($r['warnings'] as $w) {
            $this->warn("  - {$w}");
        }

        return self::SUCCESS;
    }
}
