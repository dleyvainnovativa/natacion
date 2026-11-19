<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Member;
use App\Models\ScheduleSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Inscribe (o des-inscribe) a un socio en SLOTS recurrentes.
 *
 * Un slot es la clase semanal recurrente; su roster (slot_members) lo heredan
 * las sesiones que el generador materializa a futuro. Pero las sesiones de ESTA
 * semana ya pueden estar generadas, así que al inscribir también agregamos al
 * socio a las sesiones ya creadas de hoy en adelante, para que aparezca de
 * inmediato sin esperar a la regeneración.
 */
class EnrollmentService
{
    /**
     * Inscribe al socio en los slots indicados (roster recurrente) y en sus
     * sesiones futuras ya generadas. Idempotente.
     *
     * @param  int[]  $slotIds
     * @return int  número de slots en los que quedó inscrito
     */
    public function enroll(Member $member, array $slotIds): int
    {
        $slotIds = array_values(array_unique(array_map('intval', $slotIds)));
        if (! $slotIds) {
            return 0;
        }

        $slots = ScheduleSlot::whereIn('id', $slotIds)->get();

        DB::transaction(function () use ($member, $slots) {
            foreach ($slots as $slot) {
                // Roster recurrente del slot (sesiones futuras lo heredan).
                $slot->members()->syncWithoutDetaching([$member->id]);

                // Sesiones ya generadas de hoy en adelante: agregar ya.
                ClassSession::where('schedule_slot_id', $slot->id)
                    ->where('starts_at', '>=', Carbon::today()->startOfDay())
                    ->where('status', '!=', 'cancelled')
                    ->pluck('id')
                    ->each(function ($sessionId) use ($member) {
                        DB::table('session_members')->updateOrInsert(
                            ['class_session_id' => $sessionId, 'member_id' => $member->id],
                            ['updated_at' => now(), 'created_at' => now()],
                        );
                    });
            }
        });

        return $slots->count();
    }

    /**
     * Sincroniza el roster recurrente de UN slot al set de socios dado, y
     * aplica el cambio a las sesiones ya generadas de hoy en adelante
     * (agrega los nuevos, quita los removidos). Esto arregla el bug de que el
     * roster solo aparecía "el próximo mes": ahora se refleja esta misma semana.
     *
     * @param  int[]  $memberIds
     */
    public function syncSlotRoster(ScheduleSlot $slot, array $memberIds): void
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));

        $current = $slot->members()->pluck('members.id')->all();
        $toAdd    = array_diff($memberIds, $current);
        $toRemove = array_diff($current, $memberIds);

        DB::transaction(function () use ($slot, $memberIds, $toAdd, $toRemove) {
            $slot->members()->sync($memberIds);

            if (! $toAdd && ! $toRemove) {
                return;
            }

            $sessionIds = ClassSession::where('schedule_slot_id', $slot->id)
                ->where('starts_at', '>=', Carbon::today()->startOfDay())
                ->where('status', '!=', 'cancelled')
                ->pluck('id');

            foreach ($sessionIds as $sessionId) {
                foreach ($toAdd as $mid) {
                    DB::table('session_members')->updateOrInsert(
                        ['class_session_id' => $sessionId, 'member_id' => $mid],
                        ['updated_at' => now(), 'created_at' => now()],
                    );
                }
                if ($toRemove) {
                    DB::table('session_members')
                        ->where('class_session_id', $sessionId)
                        ->whereIn('member_id', $toRemove)
                        ->delete();
                }
            }
        });
    }

    /**
     * Quita a un socio de UNA clase recurrente (slot): lo saca del roster del slot
     * y de sus sesiones futuras ya generadas. NO lo da de baja como socio, solo
     * deja de aparecer en esa clase de hoy en adelante. El histórico se conserva.
     */
    public function unenrollSlot(Member $member, int $slotId): void
    {
        DB::transaction(function () use ($member, $slotId) {
            $member->slotAssignments()->detach($slotId);

            $sessionIds = ClassSession::where('schedule_slot_id', $slotId)
                ->where('starts_at', '>=', Carbon::today()->startOfDay())
                ->pluck('id');

            DB::table('session_members')
                ->where('member_id', $member->id)
                ->whereIn('class_session_id', $sessionIds)
                ->delete();
        });
    }

    /**
     * Sincroniza EXACTAMENTE el set de slots de un socio: inscribe los nuevos y
     * quita los que ya no estén marcados (del roster recurrente y de sus
     * sesiones futuras). Se usa en la página "Inscribir a clases".
     *
     * @param  int[]  $slotIds
     */
    public function sync(Member $member, array $slotIds): void
    {
        $slotIds = array_values(array_unique(array_map('intval', $slotIds)));

        $current = $member->slotAssignments()->pluck('schedule_slots.id')->all();
        $toAdd    = array_diff($slotIds, $current);
        $toRemove = array_diff($current, $slotIds);

        if ($toAdd) {
            $this->enroll($member, $toAdd);
        }

        if ($toRemove) {
            DB::transaction(function () use ($member, $toRemove) {
                $member->slotAssignments()->detach($toRemove);

                $sessionIds = ClassSession::whereIn('schedule_slot_id', $toRemove)
                    ->where('starts_at', '>=', Carbon::today()->startOfDay())
                    ->pluck('id');

                DB::table('session_members')
                    ->where('member_id', $member->id)
                    ->whereIn('class_session_id', $sessionIds)
                    ->delete();
            });
        }
    }
}
