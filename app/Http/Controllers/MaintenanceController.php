<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceRequest;
use App\Models\MaintenanceLog;
use App\Models\Pool;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage-maintenance');

        $filter = $request->get('status', 'open'); // open | done | all

        $logs = MaintenanceLog::with('pool', 'creator')
            ->when($filter === 'open', fn ($q) => $q->open())
            ->when($filter === 'done', fn ($q) => $q->done())
            ->orderByRaw('scheduled_for IS NULL')   // con fecha primero
            ->orderBy('scheduled_for')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('maintenance.index', [
            'logs'       => $logs,
            'filter'     => $filter,
            'openCount'  => MaintenanceLog::open()->count(),
            'pools'      => Pool::orderBy('name')->get(),
        ]);
    }

    public function store(MaintenanceRequest $request)
    {
        MaintenanceLog::create($request->validated() + [
            'status'     => 'open',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('maintenance.index')
            ->with('ok', 'Tarea de mantenimiento registrada.');
    }

    public function update(MaintenanceRequest $request, MaintenanceLog $maintenance)
    {
        $maintenance->update($request->validated());

        return redirect()->route('maintenance.index')
            ->with('ok', 'Tarea actualizada.');
    }

    /** Alternar open <-> done con un clic. */
    public function toggle(MaintenanceLog $maintenance, Request $request)
    {
        $this->authorize('manage-maintenance');

        $maintenance->update([
            'status' => $maintenance->isDone() ? 'open' : 'done',
        ]);

        return back()->with('ok',
            $maintenance->isDone() ? 'Tarea marcada como hecha.' : 'Tarea reabierta.');
    }

    public function destroy(MaintenanceLog $maintenance)
    {
        $this->authorize('manage-maintenance');
        $maintenance->delete();

        return redirect()->route('maintenance.index')->with('ok', 'Tarea eliminada.');
    }
}
