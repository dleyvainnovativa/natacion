<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use Illuminate\Http\Request;

/**
 * Autogestión de maestros (instructores). Alta, edición y activar/desactivar.
 * No se borran: un maestro se desactiva para conservar el histórico de clases.
 */
class InstructorController extends Controller
{
    public function index()
    {
        $this->authorize('manage-instructors');

        $instructors = Instructor::withCount('slots')
            ->orderByDesc('active')->orderBy('name')
            ->get();

        return view('instructors.index', compact('instructors'));
    }

    public function create()
    {
        $this->authorize('manage-instructors');

        return view('instructors.form', [
            'instructor' => new Instructor(['active' => true, 'pay_per_class' => 0]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-instructors');

        Instructor::create($this->validated($request));

        return redirect()->route('instructors.index')->with('ok', 'Maestro agregado.');
    }

    public function edit(Instructor $instructor)
    {
        $this->authorize('manage-instructors');

        return view('instructors.form', compact('instructor'));
    }

    public function update(Request $request, Instructor $instructor)
    {
        $this->authorize('manage-instructors');

        $instructor->update($this->validated($request));

        return redirect()->route('instructors.index')->with('ok', 'Maestro actualizado.');
    }

    public function toggle(Instructor $instructor)
    {
        $this->authorize('manage-instructors');

        $instructor->update(['active' => ! $instructor->active]);

        return back()->with('ok', $instructor->active ? 'Maestro activado.' : 'Maestro desactivado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'pay_per_class' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['pay_per_class'] = $data['pay_per_class'] ?? 0;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
