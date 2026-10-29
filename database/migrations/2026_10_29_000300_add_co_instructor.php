<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo maestro (co-instructor) por clase. Opcional. Dos maestros pueden
 * co-impartir una clase y ambos cobran completo (decisión del cliente).
 *
 *  - schedule_slots.instructor_id_2:   co-maestro recurrente.
 *  - class_sessions.instructor_id_2:    co-maestro de la sesión fechada.
 *
 * No toca la lógica de suplencia del maestro principal (sigue igual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->foreignId('instructor_id_2')->nullable()->after('instructor_id')
                ->constrained('instructors')->nullOnDelete();
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->foreignId('instructor_id_2')->nullable()->after('actual_instructor_id')
                ->constrained('instructors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_id_2');
        });
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_id_2');
        });
    }
};
