<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días festivos / inhábiles y cierres por mantenimiento. Una fila por cierre:
 *   - start_min / end_min NULL  → todo el día festivo/inhábil.
 *   - start_min / end_min       → rango de hora (minutos desde medianoche); puede
 *     haber varias filas por día para varios rangos.
 * Aplica a todo el club. Afecta nómina, asistencia y la vista del calendario;
 * NO borra sesiones (es reversible: al quitar la marca, el día vuelve a contar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blackouts', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->unsignedSmallInteger('start_min')->nullable(); // null = todo el día
            $table->unsignedSmallInteger('end_min')->nullable();
            $table->string('type', 20)->default('festivo');        // festivo | mantenimiento
            $table->string('reason', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blackouts');
    }
};
