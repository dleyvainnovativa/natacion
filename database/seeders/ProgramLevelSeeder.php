<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramLevel;
use Illuminate\Database\Seeder;

/**
 * Siembra los niveles de programa desde config/swimfit.php (clave 'niveles').
 * Idempotente por (program_id, slug). Hoy sólo Swim Junior tiene niveles.
 */
class ProgramLevelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('swimfit.programas') as $p) {
            if (empty($p['niveles'])) {
                continue;
            }

            $program = Program::where('slug', $p['slug'])->first();
            if (! $program) {
                continue;
            }

            foreach ($p['niveles'] as $n) {
                ProgramLevel::updateOrCreate(
                    ['program_id' => $program->id, 'slug' => $n['slug']],
                    [
                        'name'         => $n['nombre'],
                        'duration_min' => $n['duracion_min'],
                        'position'     => $n['orden'] ?? 0,
                        'active'       => true,
                    ]
                );
            }
        }
    }
}
