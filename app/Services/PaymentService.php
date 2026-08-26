<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * Registra pagos de socios. Dos tipos:
 *
 *  - 'monthly' (mensualidad): registra el pago Y avanza next_billing_date del
 *    socio un mes desde su fecha vigente. Cada mensualidad cubre un mes, así
 *    que un socio con 3 meses de atraso necesita 3 pagos para ponerse al
 *    corriente (contabilidad correcta: un pago = un mes de membresía).
 *
 *  - 'one_off' (inscripción, clase suelta): solo registra, no toca la fecha de
 *    facturación.
 *
 * No procesa cobros: es un libro de lo que se pagó en caja.
 */
class PaymentService
{
    /**
     * @param  array{concept:string, amount:float, paid_on:string,
     *               type:string, period_start?:?string, period_end?:?string}  $data
     */
    public function record(Member $member, array $data, ?int $recordedBy): Payment
    {
        $paidOn = Carbon::parse($data['paid_on']);
        $isMonthly = $data['type'] === 'monthly';

        // Base para el mes cubierto: la fecha de facturación vigente (aunque
        // esté vencida) o, si no hay, la fecha de pago. Un solo cálculo para
        // que el periodo y el avance coincidan siempre.
        $base = $member->next_billing_date?->copy() ?? $paidOn->copy();

        if ($isMonthly) {
            $periodStart = $base->copy();
            $periodEnd   = $base->copy()->addMonth()->subDay();
        } else {
            $periodStart = isset($data['period_start']) ? Carbon::parse($data['period_start']) : null;
            $periodEnd   = isset($data['period_end']) ? Carbon::parse($data['period_end']) : null;
        }

        $payment = Payment::create([
            'member_id'    => $member->id,
            'concept'      => $data['concept'],
            'amount'       => $data['amount'],
            'paid_on'      => $paidOn,
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'recorded_by'  => $recordedBy,
        ]);

        // Avanzar la facturación un mes desde la MISMA base.
        if ($isMonthly) {
            $member->update([
                'next_billing_date' => $base->copy()->addMonth(),
            ]);
        }

        return $payment;
    }
}
