<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tono de color opcional por nivel. Si está vacío, el bloque usa el color del
 * programa (comportamiento actual). Paleta de 10 tonos (ver ProgramLevel::TONES).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_levels', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('program_levels', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
