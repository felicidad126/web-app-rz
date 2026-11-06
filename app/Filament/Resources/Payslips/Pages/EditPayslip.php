<?php

namespace App\Filament\Resources\Payslips\Pages;

use App\Enums\PayoutStatus;
use App\Filament\Resources\Payslips\PayslipResource;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Payroll\Payslips;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class EditPayslip extends EditRecord
{
    protected static string $resource = PayslipResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Slip gaji '.$this->payslip()->user->name;
    }

    public function getSubheading(): ?string
    {
        $payslip = $this->payslip();

        return $payslip->periodLabel().' · '.$payslip->status->getLabel().' · pendapatan '.Money::format($payslip->earning_total)
            .' · potongan '.Money::format($payslip->deduction_total).' · diterima '.Money::format($payslip->net_total);
    }

    protected function getFormActions(): array
    {
        return $this->canEditLines() ? [$this->getSaveFormAction()->label('Simpan')] : [];
    }

    public function form(Schema $schema): Schema
    {
        if ($this->canEditLines()) {
            return parent::form($schema);
        }

        // Slip final/dibayar (atau tanpa izin ubah): tabel baca saja dengan rupiah terformat, bukan form nonaktif.
        $payslip = $this->payslip();
        $payslip->loadMissing('lines');

        return $schema->components([View::make('filament.payroll.payslip-lines')->viewData(['payslip' => $payslip])]);
    }

    public function getBreadcrumb(): string
    {
        return $this->canEditLines() ? 'Ubah' : 'Lihat';
    }

    protected function afterSave(): void
    {
        $this->payslip()->recalculate();
    }

    protected function getHeaderActions(): array
    {
        $manage = fn (): bool => auth()->user()?->can('Manage:Payroll') ?? false;

        return [
            Action::make('pdf')->label('Cetak slip')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->url(fn (): string => route('payroll.payslip-pdf', $this->payslip()), shouldOpenInNewTab: true),
            Action::make('template')->label('Isi ulang dari komponen karyawan')->color('gray')
                ->visible(fn (): bool => $manage() && $this->payslip()->isDraft())
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn () => Payslips::fillFromTemplate($this->payslip()), 'Baris diisi ulang')),
            Action::make('finalize')->label('Finalkan')->icon(Heroicon::OutlinedLockClosed)
                ->visible(fn (): bool => $manage() && $this->payslip()->isDraft())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->run(fn (User $u) => Payslips::finalize($this->payslip(), $u), 'Slip difinalkan');
                }),
            Action::make('markPaid')->label('Tandai dibayar')->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => $manage() && $this->payslip()->status === PayoutStatus::Final)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn (User $u) => Payslips::markPaid($this->payslip(), $u), 'Slip ditandai dibayar')),
            Action::make('reopen')->label('Buka kembali')->color('gray')
                ->visible(fn (): bool => $manage() && $this->payslip()->status === PayoutStatus::Final)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn (User $u) => Payslips::reopen($this->payslip(), $u), 'Slip dibuka kembali')),
        ];
    }

    private function canEditLines(): bool
    {
        return $this->payslip()->isDraft() && (auth()->user()?->can('Manage:Payroll') ?? false);
    }

    private function payslip(): Payslip
    {
        /** @var Payslip $payslip */
        $payslip = $this->getRecord();
        $payslip->loadMissing('user');

        return $payslip;
    }

    private function run(callable $work, string $message): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $work($user);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Belum bisa diproses')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($message)->success()->send();
        $this->redirect(PayslipResource::getUrl('edit', ['record' => $this->getRecord()]));
    }
}
