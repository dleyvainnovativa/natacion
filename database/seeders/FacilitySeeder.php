<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Pool;
use Illuminate\Database\Seeder;

/**
 * Instalación base para poder agendar: una alberca con carriles e instructores
 * de ejemplo. Ajustar a la realidad del club (número real de carriles, nombres
 * reales de instructores) desde la BD o un panel futuro.
 */
class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $pool = Pool::firstOrCreate(['name' => 'Alberca principal']);

        // 6 carriles de ejemplo.
        for ($i = 1; $i <= 6; $i++) {
            Lane::firstOrCreate(
                ['pool_id' => $pool->id, 'label' => "Carril {$i}"],
                ['position' => $i]
            );
        }

        // Instructores vistos en el horario de referencia (ejemplo).
        foreach (['Carlos', 'Ana', 'Luis', 'Susana', 'Rafael'] as $name) {
            Instructor::firstOrCreate(
                ['name' => $name],
                ['pay_per_class' => 150.00, 'active' => true]
            );
        }
    }
}
