<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use App\Models\ScheduleSlot;
use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Radiografía del horario (SÓLO LECTURA, no cambia nada).
 *
 *   php artisan schedule:doctor
 *
 * Sirve para entender cómo quedó el horario antes/después de consolidar a
 * "para siempre": cuántas plantillas hay, cuántos slots activos/inactivos tiene
 * cada una, cuántos slots ya son "para siempre" (sin plantilla), cuántos no
 * tienen vigencia (effective_from null), cuántas clases DISTINTAS hay y cuántos
 * duplicados reales (mismo día+hora+carril+programa).
 */
class ScheduleDoctor extends Command
{
    protected $signature = 'schedule:doctor';

    protected $description = 'Radiografía del horario (sólo lectura): plantillas, slots, vigencias, duplicados.';

    public function handle(): int
    {
        $this->info('== Radiografía del horario (no cambia nada) ==');
        $this->newLine();

        // --- Plantillas ---
        $templates = ScheduleTemplate::orderBy('year')->orderBy('month')->get();
        $this->line('Plantillas (meses): ' . $templates->count());
        foreach ($templates as $t) {
            $act = ScheduleSlot::where('schedule_template_id', $t->id)->where('active', true)->count();
            $ina = ScheduleSlot::where('schedule_template_id', $t->id)->where('active', false)->count();
            $this->line(sprintf('  - %-16s activos:%-4d inactivos:%-4d', $t->display_label . ' (' . $t->key_month . ')', $act, $ina));
        }
        $this->newLine();

        // --- Slots "para siempre" (sin plantilla) ---
        $foreverActive   = ScheduleSlot::whereNull('schedule_template_id')->where('active', true)->count();
        $foreverInactive = ScheduleSlot::whereNull('schedule_template_id')->where('active', false)->count();
        $this->line("Slots 'para siempre' (sin plantilla):  activos:{$foreverActive}  inactivos:{$foreverInactive}");

        // --- Vigencias ---
        $nullFrom = ScheduleSlot::where('active', true)->whereNull('effective_from')->count();
        if ($nullFrom > 0) {
            $this->warn("  ⚠ {$nullFrom} slots ACTIVOS sin effective_from: NO generan sesiones (la consulta los excluye).");
        }

        // --- Totales de slots ---
        $totalActive   = ScheduleSlot::where('active', true)->count();
        $totalInactive = ScheduleSlot::where('active', false)->count();
        $this->newLine();
        $this->line("Slots activos en total:   {$totalActive}");
        $this->line("Slots inactivos en total: {$totalInactive}  (candidatos a recuperar con make-forever --include-inactive)");
        $this->newLine();

        // --- Clases distintas vs duplicados (sobre ACTIVOS) ---
        $this->duplicateReport('ACTIVOS', ScheduleSlot::where('active', true)->get());

        // Si hay muchos inactivos, también mostrar el panorama incluyéndolos
        // (lo que vería la recuperación).
        if ($totalInactive > 0) {
            $this->newLine();
            $this->duplicateReport('ACTIVOS + INACTIVOS (lo que recuperaría --include-inactive)', ScheduleSlot::query()->get());
        }

        // --- Sesiones ---
        $this->newLine();
        $today = Carbon::now()->startOfDay();
        $past   = ClassSession::where('starts_at', '<', $today)->count();
        $future = ClassSession::where('starts_at', '>=', $today)->count();
        $modif  = ClassSession::where('is_modified', true)->count();
        $this->line("Sesiones: pasadas:{$past}  futuras:{$future}  modificadas a mano:{$modif}");

        // --- Reparto de sesiones futuras por día de la semana ---
        $byDow = ClassSession::where('starts_at', '>=', $today)
            ->get(['starts_at'])
            ->groupBy(fn ($s) => $s->starts_at->isoWeekday())
            ->map->count();
        $dn = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $line = '  futuras por día: ';
        foreach ([1, 2, 3, 4, 5, 6, 7] as $d) {
            $line .= $dn[$d] . ':' . ($byDow[$d] ?? 0) . '  ';
        }
        $this->line($line);

        $this->newLine();
        $this->comment('Pega esta salida en el chat y te digo el siguiente paso exacto.');

        return self::SUCCESS;
    }

    private function duplicateReport(string $label, $slots): void
    {
        $groups = $slots->groupBy(fn (ScheduleSlot $s) =>
            $s->weekday . '|' . substr((string) $s->start_time, 0, 5) . '|' . ($s->lane_id ?? 'x') . '|' . $s->program_id . '|' . ($s->level_id ?? 'x')
        );
        $distinct = $groups->count();
        $dups = $groups->filter(fn ($g) => $g->count() > 1);
        $dupSlots = $dups->sum(fn ($g) => $g->count() - 1);

        $this->line("Clases DISTINTAS ({$label}): {$distinct}");
        $this->line("  grupos con duplicado: {$dups->count()}  (sobran {$dupSlots} slots repetidos)");
    }
}
