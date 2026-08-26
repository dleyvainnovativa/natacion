<?php

namespace App\Http\Controllers;

use App\Services\MemberControlReport;
use App\Services\PayrollReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Reporte de pago a instructores. */
    public function payroll(Request $request, PayrollReport $report)
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->resolveRange($request);

        $rows  = $report->build($from, $to);
        $total = $report->grandTotal($rows);

        if ($request->get('export') === 'csv') {
            return $this->payrollCsv($rows, $from, $to);
        }

        return view('reports.payroll', compact('rows', 'total', 'from', 'to'));
    }

    /** Reporte de control de socios (derecho vs asignado). */
    public function memberControl(Request $request, MemberControlReport $report)
    {
        $this->authorize('view-reports');

        $data = $report->build();

        if ($request->get('export') === 'csv') {
            return $this->memberControlCsv($data);
        }

        return view('reports.member-control', $data);
    }

    /** Rango de fechas: por defecto el mes actual. */
    private function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)
            : Carbon::now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->to)
            : Carbon::now()->endOfMonth();

        return [$from, $to];
    }

    private function payrollCsv($rows, Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = "pago_instructores_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Instructor', 'Clases impartidas', 'Como suplente', 'Propias', 'Pago por clase', 'Total']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['instructor']->name,
                    $r['classes_taught'],
                    $r['substituted_in'],
                    $r['own_classes'],
                    number_format($r['pay_per_class'], 2, '.', ''),
                    number_format($r['total'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function memberControlCsv(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Socio', 'Nombre', 'Tipo', 'Derecho (días)', 'Asignados', 'Diferencia', 'Estado']);

            foreach (['under' => 'Bajo asignado', 'over' => 'Sobre asignado'] as $key => $label) {
                foreach ($data[$key] as $r) {
                    fputcsv($out, [
                        $r['member']->socio_number,
                        $r['member']->fullName(),
                        $r['member']->membershipType?->raw_label ?? '',
                        $r['entitled'],
                        $r['assigned'],
                        $r['diff'],
                        $label,
                    ]);
                }
            }
            fclose($out);
        }, 'control_socios.csv', ['Content-Type' => 'text/csv']);
    }
}
