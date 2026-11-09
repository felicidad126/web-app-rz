<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Reports\ReportRequest;
use App\Support\BranchContext;
use App\Support\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Unduh laporan sebagai Excel, PDF, atau CSV dengan filter & batasan izin yang sama dengan halaman Laporan. */
class ReportExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->is_active && $user->can('View:Reports'), 403);

        $report = ReportRequest::make($user, $request->only(['report', 'from', 'to', 'branch', 'practitioner', 'group']));
        abort_if($report === null, 403);

        activity('access')->causedBy($user)->event('exported')
            ->withProperties(['report' => $report::key(), 'format' => $request->string('format')->toString()])->log('Download laporan '.$report::label());

        $columns = $report->columns();
        $rows = $report->rows();
        $totals = $report->totals();
        $filename = 'laporan-'.$report::key().'-'.now()->format('Ymd-Hi');

        if ($request->string('format')->toString() === 'pdf') {
            $branches = BranchContext::available($user)->whereIn('id', $report->filters()->branchIds)->pluck('name')->implode(', ');

            return Pdf::loadView('pdf.report', compact('report', 'columns', 'rows', 'totals', 'branches'))
                ->setPaper('a4', count($columns) > 6 ? 'landscape' : 'portrait')
                ->stream($filename.'.pdf');
        }

        $map = fn (array $row): array => array_map(fn ($value, array $column) => ReportRequest::excel($value, $column['type']), $row, $columns);
        $headers = array_map(fn (array $c): string => $c['label'].($c['type'] === 'percent' ? ' (%)' : ''), $columns);

        if ($request->string('format')->toString() === 'csv') {
            // Pemisah titik koma & BOM UTF-8 agar langsung terbaca rapi di Excel berbahasa Indonesia.
            return response()->streamDownload(function () use ($headers, $rows, $totals, $map): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers, ';');

                foreach ([...array_map($map, $rows), ...($totals ? [$map($totals)] : [])] as $row) {
                    fputcsv($out, array_map(fn ($v) => $v === null ? '' : $v, $row), ';');
                }

                fclose($out);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return Spreadsheet::download($filename.'.xlsx', [
            $report::label() => [
                'headers' => $headers,
                'rows' => [...array_map($map, $rows), ...($totals ? [$map($totals)] : [])],
            ],
        ]);
    }
}
