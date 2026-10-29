<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Autogestión del catálogo de programas y sus niveles. Los programas no se
 * borran (se desactivan) porque hay clases que los referencian; los niveles sí
 * se pueden eliminar (las clases que lo usaban quedan sin nivel).
 */
class ProgramController extends Controller
{
    public function index()
    {
        $this->authorize('manage-programs');

        $programs = Program::with('levels')->orderByDesc('active')->orderBy('name')->get();

        return view('programs.index', compact('programs'));
    }

    public function create()
    {
        $this->authorize('manage-programs');

        return view('programs.form', [
            'program' => new Program([
                'active' => true, 'color' => 'teal', 'audience' => 'kids',
                'duration_min' => 30, 'lane_capacity' => 6,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-programs');

        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['active'] = $request->boolean('active');

        $program = Program::create($data);

        return redirect()->route('programs.edit', $program)
            ->with('ok', 'Programa creado. Ahora puedes agregarle niveles.');
    }

    public function edit(Program $program)
    {
        $this->authorize('manage-programs');

        $program->load('levels');

        return view('programs.form', compact('program'));
    }

    public function update(Request $request, Program $program)
    {
        $this->authorize('manage-programs');

        $data = $this->validated($request);
        $data['active'] = $request->boolean('active');

        $program->update($data); // el slug no cambia

        return redirect()->route('programs.index')->with('ok', 'Programa actualizado.');
    }

    public function toggle(Program $program)
    {
        $this->authorize('manage-programs');

        $program->update(['active' => ! $program->active]);

        return back()->with('ok', $program->active ? 'Programa activado.' : 'Programa desactivado.');
    }

    // ---- Niveles ----

    public function storeLevel(Request $request, Program $program)
    {
        $this->authorize('manage-programs');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:240'],
        ]);

        $program->levels()->create([
            'slug'         => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
            'name'         => $data['name'],
            'duration_min' => $data['duration_min'],
            'position'     => ((int) $program->levels()->max('position')) + 1,
            'active'       => true,
        ]);

        return back()->with('ok', 'Nivel agregado.');
    }

    public function updateLevel(Request $request, ProgramLevel $level)
    {
        $this->authorize('manage-programs');

        $level->update($request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:240'],
        ]));

        return back()->with('ok', 'Nivel actualizado.');
    }

    public function destroyLevel(ProgramLevel $level)
    {
        $this->authorize('manage-programs');

        $level->delete();

        return back()->with('ok', 'Nivel eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'audience'      => ['required', 'in:kids,adults'],
            'age_range'     => ['nullable', 'string', 'max:60'],
            'duration_min'  => ['required', 'integer', 'min:5', 'max:240'],
            'lane_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'color'         => ['required', 'in:teal,green,blue'],
            'icon'          => ['nullable', 'string', 'max:40'],
            'summary'       => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'programa';
        $slug = $base;
        $i = 2;
        while (Program::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
