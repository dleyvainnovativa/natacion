<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use App\Models\ScheduleSlot;
use App\Models\ScheduleTemplate;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CUTOVER al horario "para siempre".
 *
 * Antes cada clase recurrente vivía dentro de una plantilla por mes, así que la
 * misma clase existía repetida (una por mes). Este comando consolida: toma las
 * clases de UN mes de referencia (por defecto el más reciente con clases),
 * las vuelve "para siempre" (vigentes desde la fecha de corte, sin fin y sin
 * plantilla) y jubila el resto.
 *
 *   php artisan schedule:make-forever --dry-run        # NO cambia nada: reporta
 *   php artisan schedule:make-forever                  # aplica (pide confirmación)
 *   php artisan schedule:make-forever --month=2026-11  # usa ese mes como base
 *   php artisan schedule:make-forever --from=2026-11-10 # fecha de corte
 *
 * Qué hace al aplicar:
 *   1. Promueve los slots ACTIVOS del mes base: effective_from = corte,
 *      effective_to = null, schedule_template_id = null, source = 'base'.
 *   2. Jubila (active=false, effective_to = corte-1) TODOS los demás slots:
 *      conserva su historial, dejan de generar.
 *   3. Borra las sesiones FUTURAS (starts_at >= corte) que NO estén marcadas a
 *      mano (is_modified=false) y que vengan de slots jubilados: así no quedan
 *      duplicados ni restos del esquema viejo. Respeta lo pasado y lo manual.
 *   4. Regenera el horizonte hacia adelante desde los slots "para siempre".
 *
 * Idempotente en la práctica: si ya no hay plantillas, no hay nada que hacer.
 */
class MakeScheduleForever extends Command
{
    protected $signature = 'schedule:make-forever
                            {--dry-run : Sólo reporta lo que haría, sin cambiar nada}
                            {--month= : Mes base YYYY-MM (default: el más reciente con clases activas)}
                            {--from= : Fecha de corte Y-m-d (default: lunes de esta semana)}';

    protected $description = 'Consolida el horario mensual a un horario base "para siempre" (con --dry-run para revisar antes).';

    public function handle(SessionGenerator $generator): int
    {
        $dry = (bool) $this->option('dry-run');

        // --- Fecha de corte ---
        $cut = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        // --- Mes base ---
        $template = $this->resolveBaseTemplate($this->option('month'));

        if (! $template) {
            $this->warn('No hay plantillas con clases activas. '
                . 'Parece que el horario ya es "para siempre" o está vacío: nada que consolidar.');
            return self::SUCCESS;
        }

        // Slots a promover (activos del mes base) y a jubilar (el resto).
        $promoteIds = ScheduleSlot::where('schedule_template_id', $template->id)
            ->where('active', true)
            ->pluck('id');

        $retireIds = ScheduleSlot::whereNotIn('id', $promoteIds)->pluck('id');

        // Sesiones futuras a borrar: de slots jubilados, no modificadas a mano.
        $futureToDelete = ClassSession::query()
            ->whereIn('schedule_slot_id', $retireIds)
            ->where('starts_at', '>=', $cut)
            ->where('is_modified', false)
            ->count();

        // --- Reporte ---
        $this->line('');
        $this->info('Plan de consolidación a horario "para siempre"');
        $this->line('  Mes base          : ' . $template->display_label . ' (' . $template->key_month . ')');
        $this->line('  Fecha de corte    : ' . $cut->format('d/m/Y'));
        $this->line('  Clases a promover : ' . $promoteIds->count() . ' (pasan a vigentes para siempre)');
        $this->line('  Clases a jubilar  : ' . $retireIds->count() . ' (dejan de generar; se conserva el historial)');
        $this->line('  Sesiones futuras que se borran: ' . $futureToDelete
            . ' (de clases jubiladas, sin cambios manuales; lo pasado y lo movido a mano se respeta)');
        $this->line('');

        if ($promoteIds->isEmpty()) {
            $this->warn('El mes base no tiene clases activas. Elige otro con --month=YYYY-MM.');
            return self::FAILURE;
        }

        if ($dry) {
            $this->comment('DRY-RUN: no se cambió nada. Revisa los números arriba y, si cuadran, '
                . 'corre el mismo comando sin --dry-run.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Esto modifica el horario y borra sesiones futuras de clases jubiladas. ¿Continuar?', false)) {
            $this->line('Cancelado. No se cambió nada.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($promoteIds, $retireIds, $cut) {
            // 1. Promover a "para siempre".
            ScheduleSlot::whereIn('id', $promoteIds)->update([
                'schedule_template_id' => null,
                'effective_from'       => $cut->toDateString(),
                'effective_to'         => null,
                'source'               => 'base',
                'active'               => true,
            ]);

            // 2. Jubilar el resto (conserva historial).
            ScheduleSlot::whereIn('id', $retireIds)->update([
                'active'       => false,
                'effective_to' => $cut->copy()->subDay()->toDateString(),
            ]);

            // 3. Borrar sesiones futuras de slots jubilados, sin tocar lo manual.
            ClassSession::query()
                ->whereIn('schedule_slot_id', $retireIds)
                ->where('starts_at', '>=', $cut)
                ->where('is_modified', false)
                ->delete();
        });

        // 4. Regenerar el horizonte desde las clases "para siempre".
        $gen = $generator->generateHorizon();

        $this->info('Listo. Horario consolidado a "para siempre".');
        $this->line('  Regeneración del horizonte: ' . $gen['created'] . ' sesiones creadas, '
            . $gen['skipped'] . ' ya existían.');
        $this->comment('Verifica el calendario de las próximas semanas antes de dar por cerrado el cambio.');

        return self::SUCCESS;
    }

    /**
     * Resuelve el mes base: el indicado con --month, o el más reciente que tenga
     * al menos una clase activa.
     */
    private function resolveBaseTemplate(?string $month): ?ScheduleTemplate
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            [$y, $m] = array_map('intval', explode('-', $month));
            return ScheduleTemplate::where('year', $y)->where('month', $m)->first();
        }

        return ScheduleTemplate::query()
            ->whereHas('slots', fn ($q) => $q->where('active', true))
            ->orderByDesc('year')->orderByDesc('month')
            ->first();
    }
}
