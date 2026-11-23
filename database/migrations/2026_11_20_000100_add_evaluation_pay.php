<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago por "evaluación": precio por instructor + conteo de evaluaciones por
 * clase (se captura en Asistencia de instructores). La nómina suma
 * evaluaciones × precio al instructor que impartió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->decimal('evaluation_price', 8, 2)->default(0)->after('pay_per_class');
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('evaluations_count')->default(0)->after('is_modified');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('evaluation_price');
        });
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('evaluations_count');
        });
    }
};
