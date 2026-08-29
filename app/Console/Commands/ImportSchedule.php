<?php

namespace App\Console\Commands;

use App\Services\ScheduleImporter;
use Illuminate\Console\Command;

/**
 * Importa las clases y rosters desde el archivo HORARIO.
 *
 *   php artisan schedule:import "storage/app/HORARIO JULIO 3.xlsx"
 *
 * Requiere que los socios ya estén importados (T1), porque el roster liga por
 * número de socio. Idempotente: re-importar reemplaza los slots source=horario.
 */
class ImportSchedule extends Command
{
    protected $signature = 'schedule:import {path : Ruta al archivo HORARIO .xlsx}';

    protected $description = 'Importa clases y rosters desde el archivo HORARIO (.xlsx).';

    public function handle(ScheduleImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");
            return self::FAILURE;
        }

        $this->info('Importando horario…');
        $r = $importer->import($path);

        $this->info(sprintf(
            '%d clases -> %d slots (%d por día); %d vínculos de roster; %d filas omitidas.',
            $r['classes'],
            $r['slots'],
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
