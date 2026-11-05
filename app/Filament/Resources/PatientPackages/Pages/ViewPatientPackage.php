<?php

namespace App\Filament\Resources\PatientPackages\Pages;

use App\Enums\InstallmentStatus;
use App\Enums\PatientPackageStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\PatientPackages\PatientPackageResource;
use App\Models\CashSession;
use App\Models\Installment;
use App\Models\PatientPackage;
use App\Models\User;
use App\Services\Billing\CashSessions;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PackageSales;
use App\Services\Billing\PaymentRecorder;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ViewPatientPackage extends ViewRecord
{
    protected static string $resource = PatientPackageResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var PatientPackage $package */
        $package = $this->getRecord();
        $package->load(['patient', 'practitioner.user', 'installments.invoice', 'items.treatment', 'usages' => fn ($q) => $q->active()->with('visit')->latest('used_at')]);

        return $schema->columns(1)->components([
            View::make('filament.clinic.patient-banner')->viewData(['patient' => $package->patient]),
            View::make('filament.billing.patient-package')->viewData([
                'package' => $package,
                'quota' => $package->remainingQuota(),
                'overdue' => $this->overdueElsewhere(),
            ]),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Paket & cicilan';
    }

    public function getSubheading(): ?string
    {
        /** @var PatientPackage $package */
        $package = $this->getRecord();

        return $package->name.($package->practitioner ? ' · '.$package->practitioner->user->name : '').' · dimulai '.$package->start_date->format('d/m/Y').' · '.$package->package_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->payInstallmentAction(),
            Action::make('cancel')
                ->label('Batalkan paket')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->visible(fn (PatientPackage $record): bool => $record->status === PatientPackageStatus::Active)
                ->authorize(fn (PatientPackage $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->modalDescription('Cicilan yang belum ditagih ikut batal. Tagihan cicilan yang sudah terbit di-void/refund lewat persetujuan owner.')
                ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(5)])
                ->action(function (PatientPackage $record, array $data): void {
                    PackageSales::cancel($record, $data['reason']);
                    Notification::make()->title('Paket dibatalkan')->success()->send();
                }),
        ];
    }

    /** Terima pembayaran cicilan (default: cicilan berikutnya). Tagihan cicilan dibuat otomatis. */
    public function payInstallmentAction(): Action
    {
        return Action::make('payInstallment')
            ->label(fn (array $arguments): string => match (true) {
                isset($arguments['installment']) => 'Terima',
                ($i = $this->installment($arguments)) !== null => 'Terima '.mb_strtolower($i->label()).' · '.Money::format($i->invoice?->balance() ?? $i->amount),
                default => 'Terima cicilan',
            })
            ->size(fn (array $arguments): string => isset($arguments['installment']) ? 'sm' : 'md')
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (array $arguments): bool => $this->installment($arguments) !== null)
            ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
            ->modalHeading(fn (array $arguments): string => $this->installment($arguments)?->label() ?? 'Cicilan')
            ->modalDescription(fn (array $arguments): string => 'Jumlah '.Money::format(($i = $this->installment($arguments)) ? ($i->invoice?->balance() ?? $i->amount) : 0)
                .' · jatuh tempo '.$this->installment($arguments)?->due_date->format('d/m/Y'))
            ->schema([
                Section::make('Sesi kasir belum dibuka')
                    ->description('Isi kas awal di laci untuk membuka sesi kasir.')
                    ->visible(fn (): bool => $this->session() === null)
                    ->schema([TextInput::make('opening_cash')->label('Kas awal')->prefix('Rp')->numeric()->minValue(0)->default(fn (): int => CashSessions::suggestedOpeningCash($this->branchId()))->required()]),
                ToggleButtons::make('method')->label('Metode')->options(PaymentMethod::moneyInOptions())->default(PaymentMethod::Qris->value)->inline()->required()->live(),
                TextInput::make('reference_no')->label('No. referensi')->maxLength(60)->visible(fn (Get $get): bool => $get('method') !== PaymentMethod::Cash->value),
                TextInput::make('cash_tendered')->label('Uang tunai diterima')->prefix('Rp')->numeric()->visible(fn (Get $get): bool => $get('method') === PaymentMethod::Cash->value),
            ])
            ->action(function (array $arguments, array $data, Action $action): void {
                $installment = $this->installment($arguments);
                /** @var User $user */
                $user = auth()->user();

                try {
                    if ($installment === null) {
                        throw new InvalidArgumentException('Cicilan tidak ditemukan.');
                    }

                    $session = $this->session() ?? CashSessions::open($user, $this->branchId(), (int) ($data['opening_cash'] ?? 0));
                    $invoice = InvoiceBuilder::forInstallment($installment, $user);
                    $transaction = PaymentRecorder::receive($invoice->patient, [$invoice->id], [[
                        'method' => $data['method'], 'amount' => $invoice->balance(), 'reference_no' => $data['reference_no'] ?? null,
                    ]], $session, $user, filled($data['cash_tendered'] ?? null) ? (int) $data['cash_tendered'] : null);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Pembayaran belum disimpan')->body($e->getMessage())->danger()->send();

                    throw new Halt;
                }

                Notification::make()->title($installment->label().' lunas')
                    ->body($transaction->change_given ? 'Kembalian '.Money::format($transaction->change_given) : null)
                    ->success()->persistent()
                    ->actions([
                        Action::make('receipt')->label('Cetak struk')->url(route('billing.receipt', $transaction), shouldOpenInNewTab: true),
                        Action::make('invoice')->label('Lihat tagihan')->url(InvoiceResource::getUrl('view', ['record' => $invoice])),
                    ])
                    ->send();

                $action->redirect(PatientPackageResource::getUrl('view', ['record' => $this->getRecord()]));
            });
    }

    private function installment(array $arguments): ?Installment
    {
        /** @var PatientPackage $package */
        $package = $this->getRecord();
        $open = [InstallmentStatus::Pending, InstallmentStatus::Billed];

        if (isset($arguments['installment'])) {
            return $package->installments()->with('invoice')->whereKey($arguments['installment'])->whereIn('status', $open)->first();
        }

        return $package->status === PatientPackageStatus::Active
            ? $package->installments()->with('invoice')->whereIn('status', $open)->orderBy('sequence')->first()
            : null;
    }

    private function session(): ?CashSession
    {
        $user = auth()->user();

        return $user instanceof User ? CashSessions::current($user, $this->branchId()) : null;
    }

    /** Pembayaran dicatat di cabang tempat kasir bekerja (bisa beda dengan cabang paket dijual). */
    private function branchId(): int
    {
        /** @var PatientPackage $package */
        $package = $this->getRecord();

        return BranchContext::id() ?? $package->branch_id;
    }

    /** @return Collection<int, Installment> cicilan lewat jatuh tempo di semua paket (cabang yang boleh diakses) */
    private function overdueElsewhere(): Collection
    {
        return Installment::query()
            ->with('patientPackage.patient')
            ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Billed])
            ->whereDate('due_date', '<', today())
            ->whereHas('patientPackage', fn ($q) => $q->where('status', PatientPackageStatus::Active)->whereIn('branch_id', BranchContext::available()->pluck('id')))
            ->orderBy('due_date')
            ->limit(8)
            ->get();
    }
}
