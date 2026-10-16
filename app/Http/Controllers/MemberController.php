<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\ScheduleSlot;
use App\Models\ScheduleTemplate;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /** Slots activos de la plantilla del mes actual, con cupo ocupado. */
    private function currentMonthSlots()
    {
        $template = ScheduleTemplate::resolveFor(Carbon::now());

        return ScheduleSlot::with(['program', 'lane', 'instructor'])
            ->withCount('members')
            ->where('schedule_template_id', $template->id)
            ->where('active', true)
            ->orderBy('weekday')->orderBy('start_time')
            ->get();
    }

    public function index(Request $request)
    {
        $members = Member::query()
            ->with('membershipType')
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('last_name_1')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $statuses = Member::query()->distinct()->pluck('status')->filter()->values();

        return view('members.index', compact('members', 'statuses'));
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
        $member->load('membershipType', 'sessions.program');

        return view('members.show', compact('member'));
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
}
