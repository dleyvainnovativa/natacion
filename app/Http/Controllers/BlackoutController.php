<?php

namespace App\Http\Controllers;

use App\Models\Blackout;
use Illuminate\Http\Request;

/**
 * Marca días festivos/inhábiles y cierres por mantenimiento desde la vista de día.
 */
class BlackoutController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('move-classes');

        $data = $request->validate([
            'date'           => ['required', 'date'],
            'type'           => ['required', 'in:festivo,mantenimiento'],
            'scope'          => ['required', 'in:all_day,ranges'],
            'reason'         => ['nullable', 'string', 'max:160'],
            'ranges'         => ['array'],
            'ranges.*.start' => ['nullable', 'date_format:H:i'],
            'ranges.*.end'   => ['nullable', 'date_format:H:i'],
        ]);

        $date = $data['date'];

        if ($data['scope'] === 'all_day') {
            Blackout::create([
                'date'       => $date,
                'type'       => $data['type'],
                'reason'     => $data['reason'] ?? null,
                'start_min'  => null,
                'end_min'    => null,
                'created_by' => $request->user()->id,
            ]);

            return back()->with('ok', 'Día marcado como ' . ($data['type'] === 'mantenimiento' ? 'mantenimiento' : 'festivo') . '.');
        }

        // Rangos de hora: una fila por rango válido (fin > inicio).
        $created = 0;
        foreach ($data['ranges'] ?? [] as $r) {
            if (empty($r['start']) || empty($r['end'])) {
                continue;
            }
            $start = $this->toMin($r['start']);
            $end   = $this->toMin($r['end']);
            if ($end <= $start) {
                continue;
            }
            Blackout::create([
                'date'       => $date,
                'type'       => $data['type'],
                'reason'     => $data['reason'] ?? null,
                'start_min'  => $start,
                'end_min'    => $end,
                'created_by' => $request->user()->id,
            ]);
            $created++;
        }

        if ($created === 0) {
            return back()->with('toast', [['msg' => 'No se guardó ningún rango válido (revisa que el fin sea mayor al inicio).', 'type' => 'error']]);
        }

        return back()->with('ok', "Se marcaron {$created} rango(s) de cierre.");
    }

    public function destroy(Blackout $blackout)
    {
        $this->authorize('move-classes');

        $blackout->delete();

        return back()->with('ok', 'Marca de cierre quitada.');
    }

    private function toMin(string $hhmm): int
    {
        [$h, $m] = explode(':', $hhmm);

        return (int) $h * 60 + (int) $m;
    }
}
