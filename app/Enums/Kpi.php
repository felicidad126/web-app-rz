<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Definisi KPI, ditulis sekali dan dipakai untuk tooltip di dashboard, briefing pagi, laporan, dan email (Planning §5.8).
 */
enum Kpi: string implements HasLabel
{
    case Production = 'production';
    case CashIn = 'cash_in';
    case CollectionRatio = 'collection_ratio';
    case ProductionVsTarget = 'production_vs_target';
    case AveragePerVisit = 'average_per_visit';
    case NewPatients = 'new_patients';
    case ChairUtilization = 'chair_utilization';
    case NoShow = 'no_show';
    case CaseAcceptance = 'case_acceptance';
    case UnscheduledTreatment = 'unscheduled_treatment';
    case Receivables = 'receivables';
    case Margin = 'margin';
    case WaitTime = 'wait_time';
    case Commission = 'commission';

    public function getLabel(): string
    {
        return match ($this) {
            self::Production => 'Produksi',
            self::CashIn => 'Kas masuk',
            self::CollectionRatio => 'Kas / produksi',
            self::ProductionVsTarget => 'Capaian target',
            self::AveragePerVisit => 'Rata-rata per kunjungan',
            self::NewPatients => 'Pasien baru',
            self::ChairUtilization => 'Utilisasi kursi',
            self::NoShow => 'Tidak datang',
            self::CaseAcceptance => 'Penerimaan rencana',
            self::UnscheduledTreatment => 'Rencana belum dijadwalkan',
            self::Receivables => 'Piutang',
            self::Margin => 'Margin tindakan',
            self::WaitTime => 'Waktu tunggu',
            self::Commission => 'Jasa dokter',
        };
    }

    public function definition(): string
    {
        return match ($this) {
            self::Production => 'Σ nilai setelah diskon baris tagihan dari kunjungan selesai (termasuk nilai paket yang terpakai, mis. kontrol ortho), menurut tanggal kunjungan. Tagihan void tidak dihitung.',
            self::CashIn => 'Σ pembayaran diterima (selain yang dibayar dari saldo deposit) + top-up deposit − refund yang diserahkan, menurut tanggal bayar. Termasuk cicilan paket.',
            self::CollectionRatio => 'Kas masuk ÷ produksi pada periode yang sama.',
            self::ProductionVsTarget => 'Produksi ÷ (target harian × hari kerja yang sudah lewat). Target harian = target bulan ÷ hari kerja bulan itu dari jadwal praktik. Merah < 85%, kuning 85–95%, hijau > 95%.',
            self::AveragePerVisit => 'Produksi ÷ jumlah kunjungan selesai.',
            self::NewPatients => 'Pasien yang kunjungan selesai pertamanya jatuh di periode ini.',
            self::ChairUtilization => 'Σ menit janji yang datang + kunjungan tanpa janji ÷ Σ menit praktik dokter menurut jadwal (dikurangi cuti/libur).',
            self::NoShow => 'Janji berstatus "tidak datang" ÷ semua janji terjadwal yang tidak dibatalkan. Nilai hilang = jumlah × rata-rata produksi per kunjungan 90 hari terakhir di cabang.',
            self::CaseAcceptance => 'Σ nilai rencana perawatan yang disetujui ÷ Σ nilai rencana yang diajukan (tanggal diajukan di periode).',
            self::UnscheduledTreatment => 'Σ nilai item rencana yang sudah disetujui, belum dikerjakan, dan pasiennya belum punya janji berikutnya.',
            self::Receivables => 'Sisa tagihan pasien (setelah porsi penjamin) per umur tagihan dari tanggal terbit: 0–30 / 31–60 / 61–90 / >90 hari.',
            self::Margin => 'Nilai setelah diskon − HPP bahan − biaya lab − jasa dokter.',
            self::WaitTime => 'Rata-rata (mulai diperiksa − datang) untuk kunjungan hari itu.',
            self::Commission => 'Σ jasa dokter yang tercatat menurut tanggal tindakan (sebelum dibayar), termasuk entri pembalik.',
        };
    }

    /** Warna capaian target (Planning §5.8). */
    public static function attainmentColor(?float $ratio): string
    {
        return match (true) {
            $ratio === null => 'gray',
            $ratio < 0.85 => 'danger',
            $ratio <= 0.95 => 'warning',
            default => 'success',
        };
    }
}
