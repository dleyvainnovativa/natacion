<?php

use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horario "para siempre": las clases recurrentes dejan de vivir por mes y pasan
 * a tener un rango de vigencia.
 *
 *  - effective_from: desde cuándo aplica la clase (no se generan sesiones antes).
 *  - effective_to:   hasta cuándo (null = para siempre).
 *
 * Backfill NO destructivo: a cada slot existente se le pone la vigencia de su
 * mes (inicio..fin), así el comportamiento queda IDÉNTICO al actual hasta que
 * corras `php artisan schedule:make-forever` para consolidar a forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('weekday');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        // Backfill por mes de la plantilla (en PHP para ser compatible con
        // MySQL y SQLite).
        foreach (ScheduleTemplate::all() as $t) {
            $from = Carbon::create($t->year, $t->month, 1)->startOfMonth();
            $to   = $from->copy()->endOfMonth();
            DB::table('schedule_slots')
                ->where('schedule_template_id', $t->id)
                ->update([
                    'effective_from' => $from->toDateString(),
                    'effective_to'   => $to->toDateString(),
                ]);
        }

        // Cualquier slot sin vigencia (sin plantilla) → desde el pasado lejano.
        DB::table('schedule_slots')->whereNull('effective_from')
            ->update(['effective_from' => '2000-01-01']);
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'effective_to']);
        });
    }
};
