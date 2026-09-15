<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas POR MES (3A).
 *
 * Antes había UNA sola plantilla global (todos los schedule_slots). Ahora cada
 * slot pertenece a una schedule_template, y cada template corresponde a un mes
 * (year + month). Al generar las sesiones de una semana, el generador usa la
 * plantilla del mes de esa semana.
 *
 * Migración de datos: creamos una plantilla para el mes actual y le asignamos
 * TODOS los slots existentes, para no perder la plantilla que ya había.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');   // 1-12
            $table->string('label')->nullable();    // p. ej. "Julio 2026" (opcional)
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        // Columna nullable primero para poder backfillear.
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->foreignId('schedule_template_id')->nullable()->after('id')
                ->constrained('schedule_templates')->cascadeOnDelete();
        });

        // Backfill: si hay slots, cuélgalos de la plantilla del mes actual.
        $hasSlots = DB::table('schedule_slots')->exists();
        if ($hasSlots) {
            $now   = now();
            $year  = (int) $now->year;
            $month = (int) $now->month;

            $templateId = DB::table('schedule_templates')->insertGetId([
                'year'       => $year,
                'month'      => $month,
                'label'      => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('schedule_slots')->update(['schedule_template_id' => $templateId]);
        }
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_template_id');
        });
        Schema::dropIfExists('schedule_templates');
    }
};
