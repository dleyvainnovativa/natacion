<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo: programas (fuente de verdad para duración, audiencia y cupo de
 * carril) y tipos de socio (cadena de facturación/derechos que viene del
 * archivo de Socios, mapea opcionalmente a un programa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();               // swim-baby, swim-junior...
            $table->string('name');
            $table->enum('audience', ['kids', 'adults']);   // niños | adultos
            $table->string('age_range')->nullable();        // "4 meses a 4 años"
            $table->unsignedSmallInteger('duration_min');   // duración de clase
            $table->unsignedTinyInteger('lane_capacity');   // 2 adultos / 6-7 niños
            $table->string('icon')->nullable();             // fa-baby...
            $table->string('color', 20)->nullable();        // blue | teal | green
            $table->text('summary')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Precios por programa (2/3/4/5 días, y por nivel cuando aplica).
        Schema::create('program_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('tier_label')->nullable();       // "Niveles 1 y 2 (30 min)"
            $table->string('concept');                      // "3 días a la semana"
            $table->unsignedSmallInteger('days_per_week')->nullable();
            $table->decimal('amount', 8, 2);
            $table->timestamps();
        });

        // Tipos de socio tal como vienen del archivo (31 variantes). Se mapean
        // a un programa cuando es posible; los casos especiales (BECA, ACUERDO)
        // quedan sin programa pero conservan su cadena y cuota.
        Schema::create('membership_types', function (Blueprint $table) {
            $table->id();
            $table->string('raw_label')->unique();          // "ADULTO 3 DIAS"
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('days_per_week')->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->decimal('default_fee', 8, 2)->nullable();
            $table->boolean('special')->default(false);     // BECA, SWIM ACUERDO, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_prices');
        Schema::dropIfExists('membership_types');
        Schema::dropIfExists('programs');
    }
};
