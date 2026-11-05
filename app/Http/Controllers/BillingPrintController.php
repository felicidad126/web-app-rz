<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Billing\ReceiptData;
use App\Support\BranchContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Struk thermal (HTML + window.print, lebar 80/58 mm) dan nota PDF A5.
 */
class BillingPrintController extends Controller
{
    public function receipt(Request $request, PaymentTransaction $transaction): View
    {
        $this->authorizeBranch($transaction->branch_id);

        return view('billing.receipt', [...ReceiptData::forTransaction($transaction), 'width' => $this->width($request)]);
    }

    public function invoiceReceipt(Request $request, Invoice $invoice): View
    {
        $this->authorizeBranch($invoice->branch_id);
        abort_if($invoice->isDraft(), 404);

        return view('billing.receipt', [...ReceiptData::forInvoice($invoice), 'width' => $this->width($request)]);
    }

    public function invoicePdf(Invoice $invoice): Response
    {
        $this->authorizeBranch($invoice->branch_id);
        abort_if($invoice->isDraft(), 404);

        $invoice->loadMissing(['branch', 'patient', 'payer', 'items', 'visit.practitioner.user', 'payments.transaction', 'issuer']);

        return Pdf::loadView('pdf.invoice-note', ['invoice' => $invoice])
            ->setPaper('a5')
            ->stream('nota-'.$invoice->invoice_number.'.pdf');
    }

    private function authorizeBranch(int $branchId): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $user->is_active && $user->can('View:Invoice') && BranchContext::available($user)->contains('id', $branchId),
            403,
        );
    }

    private function width(Request $request): int
    {
        return $request->integer('w') === 58 ? 58 : 80;
    }
}
