<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Member;
use App\Models\MembershipType;
use Illuminate\Http\Request;

class MemberController extends Controller
{
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
        ]);
    }

    public function store(MemberRequest $request)
    {
        Member::create($request->validated());

        return redirect()->route('members.index')
            ->with('ok', 'Socio registrado.');
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
