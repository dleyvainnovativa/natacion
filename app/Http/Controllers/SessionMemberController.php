<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mover un socio individual de una clase a otra (el "mover a…" del horario).
 *
 * scope:
 *  - 'date'   : solo esta sesión fechada (session_members).
 *  - 'series' : también el roster del slot recurrente, para que el cambio
 *               persista semana a semana.
 */
class SessionMemberController extends Controller
{
    public function move(Request $request, ClassSession $from)
    {
        $this->authorize('move-classes');

        $data = $request->validate([
            'member_id'  => ['required', 'exists:members,id'],
            'to_session' => ['required', 'exists:class_sessions,id', 'different:from'],
            'scope'      => ['required', 'in:date,series'],
        ]);

        $to = ClassSession::findOrFail($data['to_session']);
        $memberId = (int) $data['member_id'];

        DB::transaction(function () use ($from, $to, $memberId, $data) {
            // Nivel sesión: quitar de origen, agregar a destino.
            $from->members()->detach($memberId);
            $to->members()->syncWithoutDetaching([$memberId]);

            // Nivel serie: mover también en los rosters de los slots.
            if ($data['scope'] === 'series') {
                $from->slot?->members()->detach($memberId);
                $to->slot?->members()->syncWithoutDetaching([$memberId]);
            }
        });

        return response()->json([
            'ok'      => true,
            'message' => $data['scope'] === 'series'
                ? 'Socio movido en esta y las próximas semanas.'
                : 'Socio movido solo en esta fecha.',
        ]);
    }
}
