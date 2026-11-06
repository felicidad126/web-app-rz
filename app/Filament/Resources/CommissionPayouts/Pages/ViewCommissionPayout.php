<?php

namespace App\Filament\Resources\CommissionPayouts\Pages;

use App\Enums\PayoutStatus;
use App\Enums\SalaryComponentKind;
use App\Filament\Resources\CommissionPayouts\CommissionPayoutResource;
use App\Models\CommissionPayout;
use App\Models\CommissionPayoutAdjustment;
use App\Models\User;
use App\Services\Commission\CommissionPayouts;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewCommissionPayout extends ViewRecord
{
    protected static string $resource = CommissionPayoutResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            View::make('filament.payroll.payout')->viewData(['payout' => $this->payout()]),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Jasa '.$this->payout()->practitioner->displayName();
    }

    public function getSubheading(): ?string
    {
        $payout = $this->payout();
        $finalized = $payout->finalized_at ? ' · final '.$payout->finalized_at->format('d/m/Y').' oleh '.$payout->finalizer?->name : '';
        $paid = $payout->paid_at ? ' · dibayar '.$payout->paid_at->format('d/m/Y') : '';

        return $payout->periodLabel().' · '.$payout->status->getLabel().$finalized.$paid;
    }

    protected function getHeaderActions(): array
    {
        $manage = fn (): bool => auth()->user()?->can('Manage:Commission') ?? false;

        return [
            Action::make('pdf')
                ->label('Cetak slip')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('payroll.commission-slip', $this->payout()), shouldOpenInNewTab: true),
            Action::make('finalize')
                ->label('Finalkan')
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible(fn (): bool => $manage() && $this->payout()->isDraft())
                ->requiresConfirmation()
                ->modalDescription('Isi rekap dikunci. Slip bisa dicetak dan rekap bisa ditandai dibayar.')
                ->action(fn () => $this->run(fn (User $u) => CommissionPayouts::finalize($this->payout(), $u), 'Rekap difinalkan')),
            Action::make('markPaid')
                ->label('Tandai dibayar')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => $manage() && $this->payout()->status === PayoutStatus::Final)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn (User $u) => CommissionPayouts::markPaid($this->payout(), $u), 'Rekap ditandai dibayar')),
            ActionGroup::make([
                Action::make('addAdjustment')
                    ->label('Tambah tambahan / potongan')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->visible(fn (): bool => $manage() && $this->payout()->isDraft())
                    ->schema([
                        Select::make('kind')->label('Jenis')->options(SalaryComponentKind::class)->default(SalaryComponentKind::Deduction)->required(),
                        TextInput::make('description')->label('Keterangan')->placeholder('Mis. PPh 21, kasbon, insentif')->required()->maxLength(255),
                        TextInput::make('amount')->label('Nominal')->prefix('Rp')->numeric()->minValue(1)->required(),
                    ])
                    ->action(fn (array $data) => $this->run(fn (User $u) => CommissionPayouts::addAdjustment(
                        $this->payout(),
                        $data['kind'] instanceof SalaryComponentKind ? $data['kind'] : SalaryComponentKind::from($data['kind']),
                        $data['description'],
                        (int) $data['amount'],
                        $u,
                    ), 'Ditambahkan')),
                Action::make('removeAdjustment')
                    ->label('Hapus tambahan / potongan')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->visible(fn (): bool => $manage() && $this->payout()->isDraft() && $this->payout()->adjustments->isNotEmpty())
                    ->schema([
                        Select::make('adjustment_id')->label('Baris')->required()
                            ->options(fn (): array => $this->payout()->adjustments
                                ->mapWithKeys(fn (CommissionPayoutAdjustment $a): array => [$a->id => $a->kind->getLabel().' · '.$a->description.' · Rp '.number_format($a->amount, 0, ',', '.')])->all()),
                    ])
                    ->action(fn (array $data) => $this->run(fn (User $u) => CommissionPayouts::removeAdjustment(
                        CommissionPayoutAdjustment::query()->where('commission_payout_id', $this->payout()->id)->findOrFail($data['adjustment_id']),
                        $u,
                    ), 'Dihapus')),
                Action::make('reopen')
                    ->label('Buka kembali (draf)')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->visible(fn (): bool => $manage() && $this->payout()->status === PayoutStatus::Final)
                    ->requiresConfirmation()
                    ->action(fn () => $this->run(fn (User $u) => CommissionPayouts::reopen($this->payout(), $u), 'Rekap dibuka kembali')),
            ])->label('Lainnya')->button()->color('gray')
                ->visible(fn (): bool => $manage() && $this->payout()->status !== PayoutStatus::Paid),
        ];
    }

    private function payout(): CommissionPayout
    {
        /** @var CommissionPayout $payout */
        $payout = $this->getRecord();
        $payout->loadMissing([
            'practitioner.user', 'finalizer', 'adjustments', 'attendances.branch',
            'commissions.invoice.patient', 'commissions.invoiceItem',
        ]);

        return $payout;
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
        $this->redirect(CommissionPayoutResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
