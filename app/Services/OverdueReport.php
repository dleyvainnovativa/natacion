<?php

namespace App\Services;

use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Socios vencidos: activos (ALTA) cuya next_billing_date ya pasó. Se apoya en
 * el campo que vino del Excel de Socios y que las mensualidades avanzan.
 */
class OverdueReport
{
    public function build(): Collection
    {
        $today = Carbon::today();

        return Member::query()
            ->with('membershipType')
            ->where('status', 'ALTA')
            ->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<', $today)
            ->orderBy('next_billing_date')
            ->get()
            ->map(function (Member $m) use ($today) {
                $daysOverdue = $m->next_billing_date->diffInDays($today);
                return [
                    'member'       => $m,
                    'due_date'     => $m->next_billing_date,
                    'days_overdue' => $daysOverdue,
                    'months_owed'  => max(1, (int) ceil($daysOverdue / 30)),
                    'fee'          => $m->fee,
                ];
            });
    }

    public function totalOwed(Collection $rows): float
    {
        return (float) $rows->sum(fn ($r) => ($r['fee'] ?? 0) * $r['months_owed']);
    }
}
