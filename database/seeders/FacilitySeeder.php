<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Pool;
use Illuminate\Database\Seeder;

/**
 * Instalación base. La alberca tiene 3 carriles (cualquier programa puede usar
 * cualquier carril). Ajustado de 6 -> 3 para que la vista de día muestre solo
 * columnas reales.
 */
class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $pool = Pool::firstOrCreate(['name' => 'Alberca principal']);

        for ($i = 1; $i <= 3; $i++) {
            Lane::firstOrCreate(
                ['pool_id' => $pool->id, 'label' => "Carril {$i}"],
                ['position' => $i]
            );
        }

        // Si ya existían carriles 4-6 de una siembra anterior, quitarlos.
        Lane::where('pool_id', $pool->id)->where('position', '>', 3)->delete();

        foreach (['Carlos', 'Belem', 'Jesus', 'C. Eduardo', 'Ivan'] as $name) {
            Instructor::firstOrCreate(
                ['name' => $name],
                ['pay_per_class' => 150.00, 'active' => true]
            );
        }
    }
}
