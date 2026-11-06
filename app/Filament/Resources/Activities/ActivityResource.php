<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Support\NavGroup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/** Audit log (read-only): perubahan data dan akses data pasien. */
class ActivityResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::SETTINGS;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'aktivitas';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?string $slug = 'audit-log';

    /** Jenis log → label (kolom & filter). */
    public const LOGS = ['audit' => 'Perubahan data', 'access' => 'Akses data pasien'];

    /** Kejadian (event) → label Bahasa Indonesia. */
    public const EVENTS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'restored' => 'Dipulihkan',
        'viewed' => 'Dilihat',
        'exported' => 'Diunduh',
        'signed' => 'Ditandatangani',
        'marked_sent' => 'Ditandai terkirim',
        'mfa_reset' => 'Reset MFA',
    ];

    /** Model → nama data yang dikenal staf. */
    private const SUBJECTS = [
        'Appointment' => 'Janji temu', 'Attachment' => 'Lampiran', 'Branch' => 'Cabang', 'CashSession' => 'Sesi kasir',
        'Chair' => 'Kursi', 'Commission' => 'Jasa dokter', 'CommissionPayout' => 'Rekap jasa', 'CommissionPayoutAdjustment' => 'Penyesuaian jasa',
        'CommissionRule' => 'Aturan jasa', 'ConsentTemplate' => 'Template persetujuan', 'DepositTransaction' => 'Deposit',
        'Diagnosis' => 'Diagnosis', 'EmployeeSalaryItem' => 'Komponen gaji staf', 'InformedConsent' => 'Persetujuan tindakan',
        'Installment' => 'Cicilan', 'Invoice' => 'Tagihan', 'InvoiceItem' => 'Baris tagihan', 'Item' => 'Barang',
        'ItemCategory' => 'Kategori barang', 'ItemUnit' => 'Konversi satuan', 'LabOrder' => 'Order lab', 'MedicalRecord' => 'Rekam medis',
        'MedicalRecordAddendum' => 'Addendum rekam medis', 'OdontogramCondition' => 'Kondisi odontogram', 'OdontogramEntry' => 'Odontogram',
        'OralExamination' => 'Pemeriksaan intraoral', 'Package' => 'Paket perawatan', 'PackageUsage' => 'Pemakaian paket',
        'Patient' => 'Pasien', 'PatientPackage' => 'Paket pasien', 'PatientPayer' => 'Kepesertaan penjamin', 'Payer' => 'Penjamin',
        'Payment' => 'Pembayaran', 'PaymentTransaction' => 'Transaksi pembayaran', 'Payslip' => 'Slip gaji',
        'Practitioner' => 'Tenaga kesehatan', 'PractitionerAttendance' => 'Kehadiran dokter', 'PractitionerSchedule' => 'Jadwal praktik',
        'Prescription' => 'Resep', 'PrescriptionItem' => 'Item resep', 'Recall' => 'Recall', 'Refund' => 'Refund',
        'SalaryComponent' => 'Komponen gaji', 'ScheduleException' => 'Cuti & libur', 'StockMovement' => 'Mutasi stok',
        'StockOpname' => 'Stok opname', 'Supplier' => 'Supplier', 'Target' => 'Target produksi', 'Treatment' => 'Tindakan',
        'TreatmentBranchPrice' => 'Tarif cabang', 'TreatmentMaterial' => 'Bahan tindakan', 'TreatmentPlan' => 'Rencana perawatan',
        'TreatmentPlanItem' => 'Item rencana perawatan', 'User' => 'Pengguna', 'Visit' => 'Kunjungan',
    ];

    public static function eventLabel(?string $event): ?string
    {
        return $event === null ? null : (self::EVENTS[$event] ?? Str::headline($event));
    }

    public static function subjectLabel(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $name = class_basename($type);

        return self::SUBJECTS[$name] ?? Str::headline($name);
    }

    /** Keterangan bawaan audit berisi nama event ("created"); tampilkan dalam Bahasa Indonesia. */
    public static function descriptionLabel(Activity $record): ?string
    {
        $description = $record->getAttribute('description');

        return is_string($description) && $description === $record->getAttribute('event') ? self::eventLabel($description) : $description;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i:s'),
                TextEntry::make('causer.name')->label('Oleh')->placeholder('Sistem'),
                TextEntry::make('event')->label('Kejadian')->badge()->color('gray')->formatStateUsing(fn (?string $state): ?string => self::eventLabel($state)),
                TextEntry::make('description')->label('Keterangan')->formatStateUsing(fn (Activity $record): ?string => self::descriptionLabel($record)),
                TextEntry::make('subject_type')->label('Data')->formatStateUsing(fn (?string $state): ?string => self::subjectLabel($state)),
                TextEntry::make('subject_id')->label('ID data'),
                KeyValueEntry::make('properties.attributes')->label('Nilai baru')->columnSpanFull(),
                KeyValueEntry::make('properties.old')->label('Nilai lama')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('causer.name')->label('Oleh')->placeholder('Sistem')->searchable(),
                TextColumn::make('log_name')->label('Jenis')->badge()->color('gray')->visibleFrom('md')
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : (self::LOGS[$state] ?? $state)),
                TextColumn::make('event')->label('Kejadian')->badge()->color('gray')->visibleFrom('md')
                    ->formatStateUsing(fn (?string $state): ?string => self::eventLabel($state)),
                TextColumn::make('subject_type')->label('Data')->formatStateUsing(fn (?string $state): ?string => self::subjectLabel($state))->visibleFrom('lg'),
                TextColumn::make('subject_id')->label('ID')->alignEnd()->visibleFrom('lg'),
                TextColumn::make('description')->label('Keterangan')->limit(60)->wrap()
                    ->formatStateUsing(fn (Activity $record): ?string => self::descriptionLabel($record)),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Jenis')->options(self::LOGS),
                SelectFilter::make('event')->label('Kejadian')->options(self::EVENTS),
                SelectFilter::make('causer_id')
                    ->label('Pengguna')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $id): Builder => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
