<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca el origen de un slot para poder re-importar el HORARIO sin duplicar:
 * el importador borra los slots con source='horario' antes de recrearlos. Los
 * slots creados a mano (source='manual', el default) no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
