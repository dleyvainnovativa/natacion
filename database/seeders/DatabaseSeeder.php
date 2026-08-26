<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProgramSeeder::class,        // T0: programas + precios desde config
            MembershipTypeSeeder::class, // T0: 31 tipos de socio
            UserSeeder::class,           // T0: 1 usuario por rol
            FacilitySeeder::class,       // T2: alberca, carriles, instructores
        ]);
    }
}
