<?php

namespace App\Services\Clinic;

use App\Models\InformedConsent;
use App\Models\Visit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Menyimpan informed consent: tanda tangan (PNG) dan PDF di disk privat, isi di-snapshot.
 */
final class ConsentRecorder
{
    private const MAX_SIGNATURE_BYTES = 512 * 1024;

    /**
     * @param  array{consent_template_id?: int|null, practitioner_id: int, title: string, content: string, signer_name: string, signer_relation: string, signature: string}  $data
     */
    public static function record(Visit $visit, array $data): InformedConsent
    {
        $png = self::decodeSignature($data['signature']);
        $folder = 'consents/'.$visit->patient_id;
        $signaturePath = $folder.'/'.Str::uuid().'.png';

        Storage::disk('local')->put($signaturePath, $png);

        return DB::transaction(function () use ($visit, $data, $png, $folder, $signaturePath): InformedConsent {
            $consent = InformedConsent::create([
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'consent_template_id' => $data['consent_template_id'] ?? null,
                'practitioner_id' => $data['practitioner_id'],
                'title' => $data['title'],
                'content_snapshot' => $data['content'],
                'signer_name' => $data['signer_name'],
                'signer_relation' => $data['signer_relation'],
                'signature_path' => $signaturePath,
                'signed_at' => now(),
            ]);

            $consent->load(['patient', 'practitioner.user', 'visit.branch']);

            $pdf = Pdf::loadView('pdf.informed-consent', [
                'consent' => $consent,
                'signature' => 'data:image/png;base64,'.base64_encode($png),
            ])->setPaper('a4');

            $pdfPath = $folder.'/persetujuan-'.$consent->id.'.pdf';
            Storage::disk('local')->put($pdfPath, $pdf->output());
            $consent->update(['pdf_path' => $pdfPath]);

            return $consent;
        });
    }

    private static function decodeSignature(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new InvalidArgumentException('Tanda tangan belum diisi.');
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || strlen($binary) > self::MAX_SIGNATURE_BYTES || @getimagesizefromstring($binary) === false) {
            throw new InvalidArgumentException('Gambar tanda tangan tidak valid.');
        }

        return $binary;
    }
}
