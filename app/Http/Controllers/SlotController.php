<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlotRequest;
use App\Models\Member;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Models\ScheduleTemplate;
use App\Services\ConflictDetector;
use App\Services\SessionGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function store(SlotRequest $request, ConflictDetector $detector, SessionGenerator $generator)
    {
        $data = $request->validated();

        // Resolver la plantilla del mes indicado (o el actual) y colgar el slot.
        $monthRef = $this->monthRef($data['month'] ?? null);
        $template = ScheduleTemplate::resolveFor($monthRef);
        unset($data['month']);

        // Duración por defecto = la del programa (el usuario puede sobreescribir).
        $data['duration_min'] ??= Program::find($data['program_id'])?->duration_min ?? 30;

        $slot = $template->slots()->create($data + ['active' => true]);

        // Materializar YA las sesiones del slot: desde el inicio de la semana
        // actual (o del mes, si el mes es futuro) hasta fin del mes de la
        // plantilla. Así la clase aparece de inmediato esta semana y siguientes.
        $this->generateSlotSessions($slot, $monthRef, $generator);

        return redirect()->route('schedule.template', ['month' => $template->key_month])
            ->with('ok', 'Clase agregada a la plantilla de ' . $template->display_label . '.')
            ->with('warnings', $this->slotWarnings($slot, $detector));
    }

    public function update(SlotRequest $request, ScheduleSlot $slot, ConflictDetector $detector, SessionGenerator $generator)
    {
        $data = $request->validated();
        unset($data['month']);
        $data['duration_min'] ??= Program::find($data['program_id'])?->duration_min ?? $slot->duration_min;

        $slot->update($data);

        // Generar las sesiones futuras del slot editado (las ya existentes no se
        // tocan: respetan is_modified y la deduplicación por slot+fecha).
        $monthRef = $slot->template
            ? Carbon::create($slot->template->year, $slot->template->month, 1)
            : Carbon::now();
        $this->generateSlotSessions($slot, $monthRef, $generator);

        return redirect()->route('schedule.template', ['month' => $slot->template?->key_month])
            ->with('ok', 'Clase actualizada.')
            ->with('warnings', $this->slotWarnings($slot, $detector));
    }

    public function destroy(ScheduleSlot $slot)
    {
        $month = $slot->template?->key_month;
        // Baja lógica del slot: deja de generar sesiones, conserva el histórico.
        $slot->update(['active' => false]);

        return redirect()->route('schedule.template', ['month' => $month])
            ->with('ok', 'Clase quitada de la plantilla.');
    }

    /** 'YYYY-MM' -> Carbon (día 1); default: hoy. */
    private function monthRef(?string $month): Carbon
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        }
        return Carbon::now()->startOfMonth();
    }

    /**
     * Materializa las sesiones de un slot para que aparezca de inmediato:
     * desde el MÁXIMO entre (inicio de la semana actual) y (inicio del mes de la
     * plantilla), hasta el fin de ese mes. Un slot del mes actual se llena desde
     * esta semana (aunque sea a mitad de semana); uno de un mes futuro llena todo
     * ese mes.
     */
    private function generateSlotSessions(ScheduleSlot $slot, Carbon $monthRef, SessionGenerator $generator): void
    {
        $monthStart = $monthRef->copy()->startOfMonth();
        $monthEnd   = $monthRef->copy()->endOfMonth();
        $weekStart  = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $from = $weekStart->greaterThan($monthStart) ? $weekStart : $monthStart;

        // Si el mes ya pasó por completo, no hay nada que generar.
        if ($from->greaterThan($monthEnd)) {
            return;
        }

        $generator->generateForSlot($slot, $from, $monthEnd);
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

        return redirect()->route('schedule.template', ['month' => $slot->template?->key_month])
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
