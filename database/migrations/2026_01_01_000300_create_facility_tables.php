<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instalación e instructores.
 * Un instructor puede o no tener cuenta de usuario (login). Los que marcan
 * asistencia de alumnos necesitan user_id; los demás pueden existir solo
 * como recurso agendable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('pay_per_class', 8, 2)->default(0); // pago por clase impartida
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // "Alberca principal"
            $table->timestamps();
        });

        Schema::create('lanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->string('label');                // "Carril 1"
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lanes');
        Schema::dropIfExists('pools');
        Schema::dropIfExists('instructors');
    }
};
