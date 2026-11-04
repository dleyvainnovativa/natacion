<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlotRequest;
use App\Models\Member;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Services\ConflictDetector;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function store(SlotRequest $request, ConflictDetector $detector, SessionGenerator $generator, \App\Services\EnrollmentService $enroller)
    {
        $data = $request->validated();
        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids'], $data['month']);

        // Duración por defecto: la del NIVEL si se eligió, si no la del programa.
        if (empty($data['duration_min'])) {
            $level = ! empty($data['level_id']) ? \App\Models\ProgramLevel::find($data['level_id']) : null;
            $data['duration_min'] = $level?->duration_min ?? Program::find($data['program_id'])?->duration_min ?? 30;
        }

        // Horario "para siempre": la clase recurrente nace vigente DESDE HOY y
        // sin fin (effective_to = null). Ya no se cuelga de una plantilla mensual.
        $slot = ScheduleSlot::create($data + [
            'active'         => true,
            'source'         => 'base',
            'effective_from' => Carbon::now()->startOfDay()->toDateString(),
            'effective_to'   => null,
        ]);

        // Materializar YA el horizonte (esta semana y las próximas ~8), para que
        // la clase aparezca de inmediato y siga apareciendo semana a semana.
        $generator->generateForSlotForward($slot);

        // Roster inicial elegido en el modal: se aplica a la serie y a las
        // sesiones ya generadas.
        if ($memberIds) {
            $enroller->syncSlotRoster($slot, $memberIds);
        }

        return redirect()->route('schedule.template')
            ->with('ok', 'Clase agregada al horario base (se repite cada semana).')
            ->with('warnings', $this->slotWarnings($slot, $detector));
    }

    public function update(SlotRequest $request, ScheduleSlot $slot, ConflictDetector $detector, SessionGenerator $generator, \App\Services\EnrollmentService $enroller)
    {
        $data = $request->validated();
        $hasMembers = $request->has('member_ids');
        $memberIds  = $data['member_ids'] ?? [];
        unset($data['member_ids'], $data['month']);
        if (empty($data['duration_min'])) {
            $level = ! empty($data['level_id']) ? \App\Models\ProgramLevel::find($data['level_id']) : null;
            $data['duration_min'] = $level?->duration_min ?? Program::find($data['program_id'])?->duration_min ?? $slot->duration_min;
        }

        $slot->update($data);

        // Generar el horizonte del slot editado (las sesiones ya existentes no se
        // tocan: respetan is_modified y la deduplicación por slot+fecha).
        $generator->generateForSlotForward($slot);

        // El modal envía member_ids (precargado con el roster actual): sincroniza.
        if ($hasMembers) {
            $enroller->syncSlotRoster($slot, $memberIds);
        }

        return redirect()->route('schedule.template')
            ->with('ok', 'Clase actualizada.')
            ->with('warnings', $this->slotWarnings($slot, $detector));
    }

    public function destroy(ScheduleSlot $slot)
    {
        // Baja lógica del slot: deja de generar sesiones, conserva el histórico.
        // Se "cierra" la vigencia a hoy para no borrar lo ya impartido.
        $slot->update([
            'active'       => false,
            'effective_to' => Carbon::now()->startOfDay()->toDateString(),
        ]);

        return redirect()->route('schedule.template')
            ->with('ok', 'Clase quitada del horario base.');
    }

    /** Gestionar el roster recurrente del slot. */
    public function roster(ScheduleSlot $slot)
    {
        $this->authorize('move-classes');

        $slot->load('members', 'program');

        return view('schedule.roster', [
            'slot'    => $slot,
            'members' => Member::orderBy('last_name_1')->orderBy('first_name')->get(['id', 'first_name', 'last_name_1', 'last_name_2', 'socio_number']),
        ]);
    }

    public function updateRoster(Request $request, ScheduleSlot $slot, \App\Services\EnrollmentService $enroller)
    {
        $this->authorize('move-classes');

        $validated = $request->validate([
            'member_ids'   => ['array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        // Aplica al roster recurrente Y a las sesiones de hoy en adelante ya
        // generadas (antes solo se reflejaba al regenerar / el mes siguiente).
        $enroller->syncSlotRoster($slot, $validated['member_ids'] ?? []);

        return redirect()->route('schedule.template')
            ->with('ok', 'Roster actualizado. Se aplicó a esta semana y las próximas.');
    }

    /** Avisos de conflicto para el slot en la semana actual. */
    private function slotWarnings(ScheduleSlot $slot, ConflictDetector $detector): array
    {
        return $detector->check([
            'id'            => null,
            'starts_at'     => $slot->startsAtForWeek(Carbon::now()),
            'duration_min'  => $slot->duration_min,
            'lane_id'       => $slot->lane_id,
            'instructor_id' => $slot->instructor_id,
            'program_id'    => $slot->program_id,
            'member_ids'    => $slot->members->pluck('id')->all(),
        ]);
    }
}
