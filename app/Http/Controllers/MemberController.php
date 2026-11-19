<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\ScheduleSlot;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Clases recurrentes activas y VIGENTES hoy (horario "para siempre"), con el
     * cupo ocupado. Es la lista para inscribir socios.
     */
    private function currentMonthSlots()
    {
        $today = Carbon::now()->toDateString();

        // Clases recurrentes activas cuya vigencia no haya terminado. Incluye las
        // que empiezan a futuro (effective_from > hoy) para poder inscribir ya a
        // un socio en una clase agendada para la próxima semana (antes el filtro
        // effective_from <= hoy las escondía aunque ya salieran en el calendario).
        return ScheduleSlot::with(['program', 'lane', 'instructor', 'level'])
            ->withCount('members')
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $today))
            ->orderBy('weekday')->orderBy('start_time')
            ->get();
    }

    public function index(Request $request)
    {
        // Filtro de registro: activos (default) | bajas | todos. Permite ver y
        // reactivar socios dados de baja (soft delete).
        $reg = in_array($request->query('reg'), ['bajas', 'todos'], true)
            ? $request->query('reg')
            : 'activos';

        $members = Member::query()
            ->when($reg === 'bajas', fn ($q) => $q->onlyTrashed())
            ->when($reg === 'todos', fn ($q) => $q->withTrashed())
            ->with('membershipType')
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('last_name_1')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        // Incluye bajas para que el select de Estado no pierda opciones al
        // filtrar por "Dados de baja".
        $statuses = Member::withTrashed()->distinct()->pluck('status')->filter()->values();

        return view('members.index', compact('members', 'statuses', 'reg'));
    }

    public function create()
    {
        return view('members.form', [
            'member' => new Member(['status' => 'ALTA']),
            'types'  => MembershipType::orderBy('raw_label')->get(),
            'slots'  => $this->currentMonthSlots(),
        ]);
    }

    public function store(MemberRequest $request, EnrollmentService $enroller)
    {
        $data = $request->validated();
        $slotIds = $data['slot_ids'] ?? [];
        unset($data['slot_ids']);

        $member = Member::create($data);

        $n = $enroller->enroll($member, $slotIds);

        return redirect()->route('members.show', $member)
            ->with('ok', $n
                ? "Socio registrado e inscrito en {$n} clase(s)."
                : 'Socio registrado.');
    }

    /** Página "Inscribir a clases" para un socio existente. */
    public function enrollShow(Member $member)
    {
        return view('members.enroll', [
            'member'   => $member,
            'slots'    => $this->currentMonthSlots(),
            'selected' => $member->slotAssignments()->pluck('schedule_slots.id')->all(),
        ]);
    }

    public function enrollStore(Request $request, Member $member, EnrollmentService $enroller)
    {
        $data = $request->validate([
            'slot_ids'   => ['nullable', 'array'],
            'slot_ids.*' => ['integer', 'exists:schedule_slots,id'],
        ]);

        $enroller->sync($member, $data['slot_ids'] ?? []);

        return redirect()->route('members.show', $member)
            ->with('ok', 'Inscripción actualizada.');
    }

    public function show(Member $member)
    {
        $member->load([
            'membershipType',
            'sessions.program',
            'slotAssignments.program',
            'slotAssignments.lane',
            'slotAssignments.level:id,name',
        ]);

        return view('members.show', compact('member'));
    }

    /**
     * Quita al socio de UNA clase recurrente (sin darlo de baja). Deja de aparecer
     * en esa clase de hoy en adelante; el histórico se conserva.
     */
    public function unenroll(Member $member, ScheduleSlot $slot, EnrollmentService $enroller)
    {
        $enroller->unenrollSlot($member, $slot->id);

        return back()->with('ok', 'Socio quitado de la clase (sigue activo, no se dio de baja).');
    }

    public function edit(Member $member)
    {
        return view('members.form', [
            'member' => $member,
            'types'  => MembershipType::orderBy('raw_label')->get(),
        ]);
    }

    public function update(MemberRequest $request, Member $member)
    {
        $member->update($request->validated());

        return redirect()->route('members.show', $member)
            ->with('ok', 'Socio actualizado.');
    }

    public function destroy(Member $member)
    {
        $member->delete(); // baja lógica (soft delete)

        return redirect()->route('members.index')
            ->with('ok', 'Socio dado de baja.');
    }

    /** Reactiva (restaura) un socio dado de baja. */
    public function reactivate(Member $member)
    {
        // El binding de la ruta usa withTrashed(): $member puede venir en baja.
        if ($member->trashed()) {
            $member->restore();
            $msg = "Socio {$member->fullName()} reactivado.";
        } else {
            $msg = 'El socio ya estaba activo.';
        }

        return redirect()->route('members.index')->with('ok', $msg);
    }
}
