<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de T2 sobre el esquema de T0:
 *
 *  - slot_members: roster recurrente. Los socios se asignan al SLOT (vienen
 *    cada martes 5pm); cada sesión generada hereda este roster hacia
 *    session_members. Editar el roster del slot afecta sesiones futuras.
 *
 *  - class_sessions.is_modified: marca una sesión que fue movida/editada de
 *    forma individual ("solo esta fecha"). El generador semanal NO toca
 *    sesiones modificadas, para no borrar un cambio manual.
 *
 *  - class_sessions.notes: nota opcional al mover/cancelar ("instructor
 *    enfermo", etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['schedule_slot_id', 'member_id']);
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->boolean('is_modified')->default(false)->after('status');
            $table->text('notes')->nullable()->after('is_modified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_members');
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_modified', 'notes']);
        });
    }
};
