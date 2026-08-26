<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Member;
use App\Models\Payment;
use App\Services\OverdueReport;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Cobros recientes (todos los socios). */
    public function index(Request $request)
    {
        $this->authorize('record-payments');

        $payments = Payment::with('member', 'recorder')
            ->when($request->filled('from'), fn ($q) =>
                $q->whereDate('paid_on', '>=', Carbon::parse($request->from)))
            ->when($request->filled('to'), fn ($q) =>
                $q->whereDate('paid_on', '<=', Carbon::parse($request->to)))
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $todayTotal = Payment::whereDate('paid_on', Carbon::today())->sum('amount');

        return view('payments.index', compact('payments', 'todayTotal'));
    }

    /** Historial de pagos de un socio + formulario para registrar. */
    public function member(Member $member)
    {
        $this->authorize('record-payments');

        $payments = $member->payments()
            ->with('recorder')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get();

        return view('payments.member', compact('member', 'payments'));
    }

    /** Registrar un pago para un socio. */
    public function store(PaymentRequest $request, Member $member, PaymentService $service)
    {
        $service->record($member, $request->validated(), $request->user()->id);

        return redirect()->route('payments.member', $member)
            ->with('ok', 'Pago registrado.');
    }

    /** Reporte de socios vencidos. */
    public function overdue(OverdueReport $report)
    {
        $this->authorize('record-payments');

        $rows  = $report->build();
        $total = $report->totalOwed($rows);

        return view('payments.overdue', compact('rows', 'total'));
    }
}
