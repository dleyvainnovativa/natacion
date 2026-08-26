<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un usuario por rol para arrancar. CAMBIAR las contraseñas en producción.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administradora', 'email' => 'admin@swimfitness.mx',        'role' => 'admin'],
            ['name' => 'Coordinación',   'email' => 'coordinador@swimfitness.mx',  'role' => 'coordinator'],
            ['name' => 'Instructor',     'email' => 'instructor@swimfitness.mx',   'role' => 'instructor'],
            ['name' => 'Recepción',      'email' => 'recepcion@swimfitness.mx',    'role' => 'receptionist'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'     => $u['name'],
                    'role'     => $u['role'],
                    'active'   => true,
                    'password' => Hash::make('password'), // CAMBIAR
                ]
            );
        }
    }
}
