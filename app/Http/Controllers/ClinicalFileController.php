<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\InformedConsent;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File rekam medis di disk privat. Hanya untuk peran yang boleh membaca rekam medis; setiap akses dicatat.
 */
class ClinicalFileController extends Controller
{
    public function attachment(Attachment $attachment): StreamedResponse
    {
        $this->authorizeClinical();
        $this->logAccess($attachment, 'Membuka lampiran rekam medis');

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function consentPdf(InformedConsent $consent): StreamedResponse
    {
        $this->authorizeClinical();
        abort_if(blank($consent->pdf_path), 404);
        $this->logAccess($consent, 'Membuka PDF persetujuan tindakan');

        return Storage::disk('local')->response((string) $consent->pdf_path, 'persetujuan-'.$consent->id.'.pdf', [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeClinical(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->is_active && $user->can('View:MedicalRecord'), 403);
    }

    private function logAccess(object $subject, string $description): void
    {
        activity('access')
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->event('viewed')
            ->log($description);
    }
}
