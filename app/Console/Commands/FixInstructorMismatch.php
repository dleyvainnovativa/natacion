<?php

namespace App\Console\Commands;

use App\Models\ScheduleSlot;
use App\Services\SessionGenerator;
use Illuminate\Console\Command;

/**
 * Alinea el instructor de las sesiones FUTURAS con el de su plantilla (slot).
 *
 * Arregla el desfase histórico: al editar el instructor de un slot, las sesiones
 * ya generadas se quedaban con el instructor viejo (el calendario y la asistencia
 * mostraban a alguien que la plantilla ya había quitado). De aquí en adelante la
 * propagación es automática al editar; este comando limpia lo ya generado.
 *
 *   php artisan schedule:fix-instructors --dry-run   (reporta, no escribe)
 *   php artisan schedule:fix-instructors             (aplica)
 *
 * Sólo toca sesiones status='scheduled' + is_modified=false + de hoy en adelante,
 * así que NUNCA pisa suplentes/asistencia ('held'), clases movidas a mano, ni el
 * histórico.
 */
class FixInstructorMismatch extends Command
{
    protected $signature = 'schedule:fix-instructors {--dry-run : Solo reporta, no escribe}';

    protected $description = 'Alinea el instructor de las sesiones futuras con su plantilla (respeta suplentes, clases movidas e histórico).';

    public function handle(SessionGenerator $generator): int
    {
        $dry = (bool) $this->option('dry-run');

        $slots = ScheduleSlot::where('active', true)->get();

        $totalSessions = 0;
        $totalSlots    = 0;

        foreach ($slots as $slot) {
            $n = $generator->syncInstructorToFutureSessions($slot, null, $dry);
            if ($n > 0) {
                $totalSessions += $n;
                $totalSlots++;
                $this->line(sprintf(
                    '  slot %-5s · %s %s · %d sesión(es)',
                    $slot->id,
                    $this->weekday($slot->weekday),
                    \Illuminate\Support\Str::of((string) $slot->start_time)->substr(0, 5),
                    $n
                ));
            }
        }

        if ($totalSessions === 0) {
            $this->info('Todo alineado: ninguna sesión futura difiere de su plantilla.');

            return self::SUCCESS;
        }

        $this->newLine();
        if ($dry) {
            $this->warn("[DRY-RUN] Se ajustarían {$totalSessions} sesión(es) en {$totalSlots} clase(s). Corre sin --dry-run para aplicar.");
        } else {
            $this->info("Listo. Se ajustaron {$totalSessions} sesión(es) en {$totalSlots} clase(s).");
        }

        return self::SUCCESS;
    }

    private function weekday(int|string|null $iso): string
    {
        return [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'][(int) $iso] ?? '—';
    }
}
