<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramPrice;
use Illuminate\Database\Seeder;

/**
 * Llena programs / program_prices desde config/swimfit.php. Idempotente:
 * usa updateOrCreate por slug, así se puede re-correr sin duplicar.
 */
class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('swimfit.programas') as $p) {
            $program = Program::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name'          => $p['nombre'],
                    'audience'      => $p['audiencia'],
                    'age_range'     => $p['edad'] ?? null,
                    'duration_min'  => $p['duracion_min'],
                    'lane_capacity' => $p['cupo_carril'],
                    'icon'          => $p['icono'] ?? null,
                    'color'         => $p['color'] ?? null,
                    'summary'       => $p['resumen'] ?? null,
                    'active'        => true,
                ]
            );

            $program->prices()->delete();

            // Precios simples (baby, adultos, fitness).
            foreach ($p['precios'] ?? [] as $price) {
                ProgramPrice::create([
                    'program_id'    => $program->id,
                    'tier_label'    => null,
                    'concept'       => $price['concepto'],
                    'days_per_week' => $price['dias'] ?? null,
                    'amount'        => $price['monto'],
                ]);
            }

            // Precios por grupo/nivel (junior).
            foreach ($p['precios_grupos'] ?? [] as $group) {
                foreach ($group['precios'] as $price) {
                    ProgramPrice::create([
                        'program_id'    => $program->id,
                        'tier_label'    => $group['titulo'],
                        'concept'       => $price['concepto'],
                        'days_per_week' => $price['dias'] ?? null,
                        'amount'        => $price['monto'],
                    ]);
                }
            }
        }
    }
}
