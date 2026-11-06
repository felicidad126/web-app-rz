<?php

namespace App\Enums;

/**
 * Penyebab kunjungan tidak terkirim, dengan panduan perbaikan untuk staf (mockup "Perlu diperbaiki").
 */
enum SatuSehatIssue: string
{
    case NotConfigured = 'not_configured';
    case LocationMissing = 'location_missing';
    case PatientNikMissing = 'patient_nik_missing';
    case PatientNotFound = 'patient_not_found';
    case PractitionerNikMissing = 'practitioner_nik_missing';
    case PractitionerNotFound = 'practitioner_not_found';
    case ProcedureCodeMissing = 'procedure_code_missing';
    case NotSigned = 'not_signed';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case Unreachable = 'unreachable';
    case AuthFailed = 'auth_failed';

    public function title(): string
    {
        return match ($this) {
            self::NotConfigured => 'Koneksi SATUSEHAT belum diatur',
            self::LocationMissing => 'Cabang belum punya Location ID SATUSEHAT',
            self::PatientNikMissing => 'Pasien belum punya NIK',
            self::PatientNotFound => 'NIK pasien tidak ditemukan di SATUSEHAT',
            self::PractitionerNikMissing => 'Dokter belum punya NIK',
            self::PractitionerNotFound => 'NIK dokter tidak ditemukan di SATUSEHAT',
            self::ProcedureCodeMissing => 'Tindakan belum punya kode ICD-9-CM',
            self::NotSigned => 'Rekam medis belum ditandatangani',
            self::Rejected => 'Ditolak SATUSEHAT',
            self::Duplicate => 'Sudah ada di SATUSEHAT',
            self::Unreachable => 'SATUSEHAT tidak bisa dihubungi',
            self::AuthFailed => 'Kredensial SATUSEHAT ditolak',
        };
    }

    public function fix(): string
    {
        return match ($this) {
            self::NotConfigured => 'Isi SATUSEHAT_ORGANIZATION_ID, CLIENT_ID, dan CLIENT_SECRET di .env server, lalu kirim ulang.',
            self::LocationMissing => 'Buka Organisasi → Cabang, isi Location ID atau klik "Daftarkan ke SATUSEHAT", lalu kirim ulang.',
            self::PatientNikMissing => 'Lengkapi NIK di data pasien; IHS dicari otomatis saat kirim ulang.',
            self::PatientNotFound => 'Periksa NIK pasien (16 digit, sesuai KTP/KK). Bila benar, pasien mungkin belum terdaftar di SATUSEHAT.',
            self::PractitionerNikMissing => 'Lengkapi NIK di data nakes (Organisasi → Nakes), lalu kirim ulang.',
            self::PractitionerNotFound => 'Periksa NIK dokter; pastikan terdaftar sebagai nakes di SATUSEHAT (SISDMK).',
            self::ProcedureCodeMissing => 'Petakan kode ICD-9-CM di Master Klinis → Tindakan & Tarif, lalu kirim ulang.',
            self::NotSigned => 'Minta dokter menandatangani rekam medis; kunjungan dikirim otomatis setelahnya.',
            self::Rejected => 'Baca pesan dari SATUSEHAT di bawah, perbaiki datanya, lalu kirim ulang.',
            self::Duplicate => 'Kemungkinan sudah terkirim saat koneksi terputus. Cek di portal SATUSEHAT; bila benar sudah ada, klik "Tandai sudah diterima".',
            self::Unreachable => 'Dicoba ulang otomatis. Bila berlangsung lama, cek koneksi internet server atau status SATUSEHAT.',
            self::AuthFailed => 'Periksa CLIENT_ID/CLIENT_SECRET dan lingkungan (sandbox/production) di .env.',
        };
    }

    /** Masalah data yang hanya selesai setelah staf memperbaiki (bukan dicoba ulang otomatis). */
    public function needsHumanFix(): bool
    {
        return ! in_array($this, [self::Unreachable], true);
    }
}
