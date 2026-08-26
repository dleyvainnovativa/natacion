<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Socios. socio_number es la llave del negocio (viene del archivo Excel) y
 * es la que usa el importador para hacer upsert. Nombre partido en 3 campos
 * como en el archivo original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('socio_number')->unique();  // "Número de socio"
            $table->string('last_name_1');                      // Apellido 1
            $table->string('last_name_2')->nullable();          // Apellido 2 ("X" -> null)
            $table->string('first_name');                       // Nombre
            $table->foreignId('membership_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('next_billing_date')->nullable();      // "Fecha siguiente generación"
            $table->string('status')->default('ALTA')->index(); // Estado socio
            $table->decimal('fee', 8, 2)->nullable();           // Importe cuota
            $table->timestamps();

            $table->index(['last_name_1', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
