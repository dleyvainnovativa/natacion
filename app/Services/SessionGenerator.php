<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\ScheduleSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Materializa las sesiones fechadas de una semana a partir de los slots
 * activos (la plantilla recurrente).
 *
 * Reglas:
 *  - Idempotente: si ya existe una sesión para (slot, fecha/hora), no la
 *    duplica.
 *  - Respeta lo manual: una sesión con is_modified = true (movida o editada
 *    "solo esta fecha") NO se regenera ni se pisa.
 *  - Hereda el roster: cada sesión nueva copia los socios del roster del slot
 *    hacia session_members.
 *  - actual_instructor_id arranca igual al programado; el coordinador lo
 *    cambia si hay suplente (T3).
 */
class SessionGenerator
{
    /**
     * Genera las sesiones de la semana que contiene $reference.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateWeek(Carbon $reference): array
    {
        $created = 0;
        $skipped = 0;

        $slots = ScheduleSlot::with('members:id')->where('active', true)->get();

        DB::transaction(function () use ($slots, $reference, &$created, &$skipped) {
            foreach ($slots as $slot) {
                $startsAt = $slot->startsAtForWeek($reference);

                // ¿Ya existe una sesión para este slot en esta fecha/hora?
                $exists = ClassSession::where('schedule_slot_id', $slot->id)
                    ->where('starts_at', $startsAt)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $session = ClassSession::create([
                    'schedule_slot_id'        => $slot->id,
                    'program_id'              => $slot->program_id,
                    'lane_id'                 => $slot->lane_id,
                    'scheduled_instructor_id' => $slot->instructor_id,
                    'actual_instructor_id'    => $slot->instructor_id,
                    'starts_at'               => $startsAt,
                    'duration_min'            => $slot->duration_min,
                    'status'                  => 'scheduled',
                    'is_modified'             => false,
                ]);

                // Heredar roster del slot -> session_members.
                $memberIds = $slot->members->pluck('id')->all();
                if ($memberIds) {
                    $session->members()->sync($memberIds);
                }

                $created++;
            }
        });

        return compact('created', 'skipped');
    }
}
