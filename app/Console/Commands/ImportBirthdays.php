<?php

namespace App\Console\Commands;

use App\Services\BirthdayImporter;
use Illuminate\Console\Command;

/**
 * Carga las fechas de nacimiento de los socios desde un archivo aparte
 * (Excel .xlsx o CSV), emparejando por NÚMERO DE SOCIO. No crea socios nuevos:
 * si el número no existe, lo reporta y sigue.
 *
 *   php artisan members:import-birthdays storage/app/cumpleanos.xlsx
 *   php artisan members:import-birthdays storage/app/cumpleanos.csv --dry-run
 *
 * El archivo sólo necesita dos columnas reconocibles: una de número de socio y
 * otra de fecha de nacimiento (acepta encabezados como "Fecha de nacimiento",
 * "Nacimiento", "Cumpleaños"). El encabezado puede no estar en la primera fila.
 */
class ImportBirthdays extends Command
{
    protected $signature = 'members:import-birthdays
                            {file : Ruta al archivo .xlsx o .csv con número de socio + fecha de nacimiento}
                            {--dry-run : Sólo reporta, sin guardar}';

    protected $description = 'Carga fechas de nacimiento de socios por número de socio (no crea socios nuevos).';

    public function handle(BirthdayImporter $importer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("No se encontró el archivo: {$file}");
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $result = $importer->import($file, $dry);
        $this->report($result);

        if ($dry) {
            $this->comment('DRY-RUN: no se guardó nada. Revisa los números y, si cuadran, '
                . 'corre el mismo comando sin --dry-run.');
        }

        return self::SUCCESS;
    }

    private function report(array $r): void
    {
        $this->info('Importación de cumpleaños terminada:');
        $this->line('  Emparejados (socio existe): ' . $r['matched']);
        $this->line('  Actualizados               : ' . $r['updated']);
        $this->line('  No encontrados (omitidos)  : ' . $r['not_found']);
        $this->line('  Filas saltadas             : ' . $r['skipped']);

        if (! empty($r['errors'])) {
            $this->warn('  Avisos (' . count($r['errors']) . '):');
            foreach (array_slice($r['errors'], 0, 20) as $e) {
                $this->line('    - ' . $e);
            }
            if (count($r['errors']) > 20) {
                $this->line('    … y ' . (count($r['errors']) - 20) . ' más.');
            }
        }
    }
}
