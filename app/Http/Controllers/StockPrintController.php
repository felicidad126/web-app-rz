<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\User;
use App\Support\BranchContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Berita acara mutasi stok (penerimaan, pemusnahan, koreksi opname, transfer) dalam PDF A4,
 * dengan blok tanda tangan pembuat dan penyetuju.
 */
class StockPrintController extends Controller
{
    public function movementPdf(StockMovement $movement): Response
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $user->is_active && $user->can('View:Stock')
                && BranchContext::available($user)->contains('id', $movement->branch_id),
            403,
        );

        $movement->loadMissing([
            'branch', 'supplier', 'counterpartBranch', 'creator', 'approver', 'reverses',
            'lines.item.baseUnit', 'lines.batch', 'lines.enteredUnit',
        ]);

        return Pdf::loadView('pdf.stock-movement', ['movement' => $movement])
            ->setPaper('a4')
            ->stream('berita-acara-'.$movement->number.'.pdf');
    }
}
