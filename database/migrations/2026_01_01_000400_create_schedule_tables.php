<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El corazón del sistema. Dos niveles:
 *
 *  - schedule_slots: la plantilla semanal recurrente (qué programa, con qué
 *    instructor, qué día, a qué hora, en qué carril). Es "el plan".
 *
 *  - class_sessions: una instancia fechada de un slot ("lo que pasó ese día").
 *    Un comando semanal materializa las sesiones de la próxima semana desde
 *    los slots. Aquí vive la regla de sustitución: scheduled_instructor_id es
 *    quien debía dar la clase; actual_instructor_id es quien la dio (por
 *    default el mismo, se sobreescribe si el coordinador marca retardo y
 *    asigna suplente). El pago se calcula sobre actual_instructor_id.
 *
 *  - session_members: qué socios están inscritos en esa sesión. Contra esta
 *    tabla se cuenta el cupo del carril (capacidad del programa) y se marca
 *    la asistencia de alumnos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lane_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('weekday');   // 1=lunes ... 7=domingo (ISO)
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_min'); // copiado del programa al crear
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['weekday', 'start_time']);
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lane_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scheduled_instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->foreignId('actual_instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_min');
            // scheduled | held | cancelled  (held = se impartió)
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();

            $table->index(['starts_at']);
        });

        Schema::create('session_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_session_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_members');
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('schedule_slots');
    }
};
