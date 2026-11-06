<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Reports\ReportRequest;
use App\Support\BranchContext;
use App\Support\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Unduh laporan sebagai Excel atau PDF dengan filter & batasan izin yang sama dengan halaman Laporan. */
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

        return Spreadsheet::download($filename.'.xlsx', [
            $report::label() => [
                'headers' => array_map(fn (array $c): string => $c['label'].($c['type'] === 'percent' ? ' (%)' : ''), $columns),
                'rows' => [...array_map($map, $rows), ...($totals ? [$map($totals)] : [])],
            ],
        ]);
    }
}
