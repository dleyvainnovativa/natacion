<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de nacimiento del socio (opcional). Permite mostrar la edad en el
 * roster de la clase, la ficha del socio, la lista de socios y el bloque del
 * calendario. El archivo de socios actual no la trae: se carga aparte con
 * `php artisan members:import-birthdays` (sólo actualiza por número de socio,
 * nunca crea socios nuevos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('birth_date');
        });
    }
};
