<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas diseñadas ahora, usadas en fases posteriores (T3-T6). Se crean en
 * T0 para que el modelo de datos quede completo y no haya que reescribir
 * migraciones ni relaciones más adelante.
 */
return new class extends Migration
{
    public function up(): void
    {
        // T3: el instructor marca asistencia de cada alumno de la sesión.
        Schema::create('member_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('present'); // present | absent | excused
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['class_session_id', 'member_id']);
        });

        // T3: el coordinador marca asistencia del instructor. Si llega tarde,
        // registra suplente aquí y el sistema copia el suplente a
        // class_sessions.actual_instructor_id (regla de pago).
        Schema::create('instructor_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('on_time'); // on_time | late | absent
            $table->foreignId('substitute_instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();
        });

        // T5: pagos de socios (mensualidades y clases sueltas).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('concept');                 // "Mensualidad", "Inscripción"...
            $table->decimal('amount', 8, 2);
            $table->date('paid_on');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // T6: bitácora de mantenimiento (alberca/equipo).
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status')->default('open');  // open | done
            $table->date('scheduled_for')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('instructor_attendances');
        Schema::dropIfExists('member_attendances');
    }
};
