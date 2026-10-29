<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Niveles de programa (Swim Junior) + programa Nado Libre.
 *
 *  - program_levels: nombre + duración por nivel; el cupo lo pone el programa.
 *  - schedule_slots.level_id / class_sessions.level_id: la clase puede apuntar
 *    a un nivel (opcional; sólo Junior hoy).
 *
 * Los datos (Nado Libre y los 7 niveles) se siembran reutilizando los seeders,
 * que leen config/swimfit.php: una sola fuente de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->unsignedSmallInteger('duration_min');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['program_id', 'slug']);
        });

        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->after('program_id')
                ->constrained('program_levels')->nullOnDelete();
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->after('program_id')
                ->constrained('program_levels')->nullOnDelete();
        });

        // Datos: Nado Libre (programa nuevo) + niveles de Junior, desde config.
        (new \Database\Seeders\ProgramSeeder())->run();
        (new \Database\Seeders\ProgramLevelSeeder())->run();
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });
        Schema::dropIfExists('program_levels');

        DB::table('programs')->where('slug', 'nado-libre')->delete();
    }
};
