<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El archivo Excel no trae contacto, pero la ficha de socio en el sistema sí
 * lo necesita. Campos opcionales para no romper la importación (que nunca los
 * llena) y permitir capturarlos a mano desde el CRUD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('first_name');
            $table->string('email')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('fee');
            $table->softDeletes()->after('updated_at'); // baja lógica, no borrar histórico
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'email', 'notes']);
        });
    }
};
