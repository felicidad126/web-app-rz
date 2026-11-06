<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\Clinic\OdontogramChart;
use App\Services\Clinic\OdontogramVisual;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** Cetak odontogram pasien (PDF) beserta indeks DMF-T dan riwayat catatan. */
class OdontogramPrintController extends Controller
{
    public function __invoke(Patient $patient): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->is_active && $user->can('View:MedicalRecord'), 403);

        activity('access')->performedOn($patient)->causedBy($user)->event('viewed')->log('Mencetak odontogram');

        $chart = new OdontogramChart($patient);

        return Pdf::loadView('pdf.odontogram', [
            'patient' => $patient,
            'teeth' => $chart->teeth(),
            'svg' => base64_encode((new OdontogramVisual($chart))->svg()),
            'indices' => $chart->indices(),
            'exam' => $patient->oralExaminations()->latest('id')->first(),
            'entries' => $patient->odontogramEntries()->with(['condition', 'visit'])->latest('id')->limit(80)->get(),
        ])->setPaper('a4')->stream('odontogram-'.$patient->rm_number.'.pdf');
    }
}
