<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlotRequest;
use App\Models\Member;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Services\ConflictDetector;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function store(SlotRequest $request, ConflictDetector $detector)
    {
        $data = $request->validated();

        // Duración por defecto = la del programa (el usuario puede sobreescribir).
        $data['duration_min'] ??= Program::find($data['program_id'])?->duration_min ?? 30;

        $slot = ScheduleSlot::create($data + ['active' => true]);

        return redirect()->route('schedule.template')
            ->with('ok', 'Clase agregada a la plantilla.')
            ->with('warnings', $this->slotWarnings($slot, $detector));
    }

    public function update(SlotRequest $request, ScheduleSlot $slot, ConflictDetector $detector)
    {
        $data = $request->validated();
        $data['duration_min'] ??= Program::find($data['program_id'])?->duration_min ?? $slot->duration_min;

        $slot->update($data);

        return redirect()->route('schedule.template')
            ->with('ok', 'Clase actualizada.')
            ->with('warnings', $this->slotWarnings($slot, $detector));
    }

    public function destroy(ScheduleSlot $slot)
    {
        // Baja lógica del slot: deja de generar sesiones, conserva el histórico.
        $slot->update(['active' => false]);

        return redirect()->route('schedule.template')
            ->with('ok', 'Clase quitada de la plantilla.');
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

    public function updateRoster(Request $request, ScheduleSlot $slot)
    {
        $this->authorize('move-classes');

        $validated = $request->validate([
            'member_ids'   => ['array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $slot->members()->sync($validated['member_ids'] ?? []);

        return redirect()->route('schedule.template')
            ->with('ok', 'Roster actualizado. Las próximas sesiones lo heredarán.');
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
