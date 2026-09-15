<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Member;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra clases (slots) de DEMO por programa y enrola socios según su tipo de
 * membresía (que ya apunta a un programa). Pensado para poblar el calendario en
 * desarrollo/demo sin depender del archivo HORARIO real.
 *
 *   php artisan db:seed --class=Database\\Seeders\\ClassScheduleSeeder
 *
 * Idempotente: usa source='seed' y borra solo esos slots antes de recrear, así
 * NUNCA toca los slots importados de verdad (source='horario') ni los creados a
 * mano en la plantilla.
 *
 * Requisitos previos: ProgramSeeder, FacilitySeeder, y socios ya importados
 * (con su membership_type -> program_id).
 */
class ClassScheduleSeeder extends Seeder
{
    /** Patrones de días típicos (ISO: 1=Lun … 7=Dom), como en el HORARIO real. */
    private const PATTERNS = [
        [1, 3, 5],  // L-M-V
        [2, 4],     // M-J
        [1, 3],     // L-M
    ];

    /** Horas de inicio candidatas por audiencia (para que se vea realista). */
    private const START_TIMES = [
        'kids'   => ['09:00', '10:00', '16:00', '17:00'],
        'adults' => ['07:00', '08:00', '19:00', '20:00'],
    ];

    public function run(): void
    {
        $programs    = Program::where('active', true)->get();
        $instructors = Instructor::where('active', true)->get();
        $lanes       = Lane::orderBy('position')->get();

        if ($programs->isEmpty() || $lanes->isEmpty()) {
            $this->command?->warn('ClassScheduleSeeder: faltan programas o carriles. Corre ProgramSeeder y FacilitySeeder primero.');
            return;
        }

        // Plantilla del mes actual (crea/clona si hace falta).
        $template = ScheduleTemplate::resolveFor(Carbon::now()->startOfMonth());

        // Socios agrupados por programa (vía su tipo de membresía).
        $membersByProgram = Member::query()
            ->whereHas('membershipType', fn ($q) => $q->whereNotNull('program_id'))
            ->with('membershipType:id,program_id')
            ->get()
            ->groupBy(fn ($m) => $m->membershipType->program_id);

        DB::transaction(function () use ($template, $programs, $instructors, $lanes, $membersByProgram) {
            // Idempotencia: limpiar solo lo sembrado antes en esta plantilla.
            $oldSlots = ScheduleSlot::where('schedule_template_id', $template->id)
                ->where('source', 'seed')->get();
            foreach ($oldSlots as $s) {
                $s->members()->detach();
                $s->delete();
            }

            $laneIdx = 0;
            $instrIdx = 0;
            $totalSlots = 0;
            $totalEnrolled = 0;

            foreach ($programs as $program) {
                $audience = $program->audience === 'kids' ? 'kids' : 'adults';
                $times = self::START_TIMES[$audience];

                // Roster disponible para este programa (socios de ese programa).
                $pool = ($membersByProgram->get($program->id) ?? collect())->values();
                $poolCursor = 0;

                // 3–4 clases por programa: elegir combinaciones día-patrón/hora.
                $slotCount = 3 + ($program->id % 2); // 3 o 4, determinista.
                for ($n = 0; $n < $slotCount; $n++) {
                    $pattern = self::PATTERNS[$n % count(self::PATTERNS)];
                    $time    = $times[$n % count($times)];
                    $lane    = $lanes[$laneIdx % $lanes->count()];
                    $instr   = $instructors->isNotEmpty() ? $instructors[$instrIdx % $instructors->count()] : null;
                    $laneIdx++; $instrIdx++;

                    // Roster para ESTA clase: hasta cupo_carril socios del pool,
                    // en round-robin para repartir entre las clases del programa.
                    $capacity = max(1, (int) $program->lane_capacity);
                    $roster = [];
                    for ($c = 0; $c < $capacity && $pool->isNotEmpty(); $c++) {
                        $roster[] = $pool[$poolCursor % $pool->count()]->id;
                        $poolCursor++;
                    }
                    $roster = array_values(array_unique($roster));

                    // Un slot por cada día del patrón; el roster va a todos.
                    foreach ($pattern as $weekday) {
                        $slot = $template->slots()->create([
                            'program_id'    => $program->id,
                            'instructor_id' => $instr?->id,
                            'lane_id'       => $lane->id,
                            'weekday'       => $weekday,
                            'start_time'    => $time,
                            'duration_min'  => $program->duration_min,
                            'active'        => true,
                            'source'        => 'seed',
                        ]);

                        if ($roster) {
                            $slot->members()->sync($roster);
                            $totalEnrolled += count($roster);
                        }
                        $totalSlots++;
                    }
                }
            }

            $this->command?->info(sprintf(
                'ClassScheduleSeeder: plantilla "%s" — %d slots creados, %d vínculos de roster.',
                $template->display_label, $totalSlots, $totalEnrolled
            ));
        });

        $this->command?->line('  Genera las sesiones con: php artisan schedule:generate-week');
    }
}
