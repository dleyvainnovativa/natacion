<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Limpia del calendario las sesiones FUTURAS "huérfanas": las que ya no
 * corresponden al horario base actual porque su clase fue dada de baja, su slot
 * quedó fuera de vigencia, o el slot ya no existe. Nunca toca sesiones pasadas
 * ni sesiones con asistencia registrada.
 *
 *   php artisan schedule:prune-orphans --dry-run   (reporta, no borra)
 *   php artisan schedule:prune-orphans             (borra)
 *
 * Úsalo una vez para recoger lo que quedó huérfano de antes; de aquí en
 * adelante, quitar una clase del horario base ya limpia sus sesiones futuras
 * sola (SlotController::destroy).
 */
class PruneOrphanSessions extends Command
{
    protected $signature = 'schedule:prune-orphans {--dry-run : Solo reporta, no borra}';

    protected $description = 'Quita del calendario las sesiones futuras huérfanas (clase dada de baja o fuera de vigencia), conservando histórico y asistencia.';

    public function handle(SessionGenerator $generator): int
    {
        $today = Carbon::now()->startOfDay();
        $dry   = (bool) $this->option('dry-run');

        $orphans = ClassSession::with('slot')
            ->where('starts_at', '>=', $today)
            ->whereDoesntHave('memberAttendances')
            ->whereDoesntHave('instructorAttendance')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (ClassSession $s) => $this->isOrphan($s, $today));

        if ($orphans->isEmpty()) {
            $this->info('No hay sesiones futuras huérfanas. El calendario ya refleja el horario base.');

            return self::SUCCESS;
        }

        $this->warn(($dry ? '[DRY-RUN] ' : '') . "Sesiones futuras huérfanas: {$orphans->count()}");

        foreach ($orphans->take(40) as $s) {
            $reason = $s->slot
                ? ($s->slot->active ? 'fuera de vigencia' : 'clase dada de baja')
                : 'slot borrado';

            $this->line(sprintf(
                '  %s · prog:%s · carril:%s · %s',
                $s->starts_at->format('D d/m H:i'),
                $s->program_id ?? '—',
                $s->lane_id ?? '—',
                $reason
            ));
        }

        if ($orphans->count() > 40) {
            $this->line('  … y ' . ($orphans->count() - 40) . ' más.');
        }

        if ($dry) {
            $this->newLine();
            $this->comment('Dry-run: no se borró nada. Corre sin --dry-run para limpiar.');

            return self::SUCCESS;
        }

        $removed = $generator->deleteSessions($orphans->pluck('id')->all());
        $this->info("Listo. Se eliminaron {$removed} sesión(es) futura(s) huérfana(s).");

        return self::SUCCESS;
    }

    /**
     * Una sesión futura es huérfana si HOY no la regeneraría el horario base:
     * su slot no existe, está inactivo, o la fecha cae fuera de su vigencia.
     */
    private function isOrphan(ClassSession $s, Carbon $today): bool
    {
        $slot = $s->slot;

        if (! $slot) {
            return true;                 // slot borrado
        }

        if (! $slot->active) {
            return true;                 // clase dada de baja
        }

        $d = $s->starts_at->copy()->startOfDay();

        if ($slot->effective_from && $d->lt($slot->effective_from->copy()->startOfDay())) {
            return true;
        }

        if ($slot->effective_to && $d->gt($slot->effective_to->copy()->endOfDay())) {
            return true;                 // fuera de vigencia
        }

        return false;
    }
}
